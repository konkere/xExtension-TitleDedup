<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/TitleNormalizer.php';
require_once __DIR__ . '/lib/RuleRepository.php';
require_once __DIR__ . '/lib/DedupDAO.php';
require_once __DIR__ . '/lib/DedupService.php';

final class TitleDedupExtension extends Minz_Extension
{
    private const CONFIG_KEY = 'title_dedup_rules_json';
    /** Whether the one-off backfill has run since the rules were last saved. */
    private const BACKFILL_KEY = 'title_dedup_backfill_done';

    private ?DedupService $service = null;
    /** @var list<array{id:string,name:string,label:string}>|null */
    private ?array $feedOptionsCache = null;
    /** @var array<string,list<string>>|null */
    private ?array $feedNameMapCache = null;

    #[\Override]
    public function init(): void
    {
        parent::init();
        $this->registerTranslates();

        $this->registerHook('entry_before_insert', [$this, 'onEntryBeforeInsert']);
        $this->registerHook('freshrss_user_maintenance', [$this, 'onUserMaintenance']);
    }

    #[\Override]
    public function handleConfigureAction(): void
    {
        $this->registerTranslates();
        if (!Minz_Request::isPost()) {
            return;
        }

        $rulesPost = $_POST['rules'] ?? null;
        if (is_array($rulesPost) && isset($_POST['rule_action'])) {
            $rulesPost = $this->applyUiAction($rulesPost, (string)$_POST['rule_action']);
        }
        $parsed = is_array($rulesPost)
            ? RuleRepository::buildConfigFromForm($rulesPost)
            : null;
        if ($parsed === null) {
            $rulesJson = Minz_Request::paramString('rules_json', true);
            $parsed = RuleRepository::parseConfig($rulesJson);
        }
        if ($parsed === null) {
            Minz_Request::setBadNotification(_t('ext.title_dedup.notify.invalid'));
            return;
        }

        $config = $this->getUserConfiguration();
        $config[self::CONFIG_KEY] = json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // Rules changed: let the next maintenance run check the whole lookback window once.
        $config[self::BACKFILL_KEY] = false;
        $this->setUserConfiguration($config);
        $this->service = null;
        Minz_Request::setGoodNotification(_t('ext.title_dedup.notify.saved'));
    }

    /**
     * @param array<mixed> $rulesPost
     * @return array<mixed>
     */
    private function applyUiAction(array $rulesPost, string $action): array
    {
        if ($action === 'add_rule') {
            array_unshift($rulesPost, $this->blankRulePost());
            return $rulesPost;
        }

        if (strpos($action, 'remove_rule:') === 0) {
            $ruleIndex = (int)substr($action, strlen('remove_rule:'));
            if (isset($rulesPost[$ruleIndex])) {
                unset($rulesPost[$ruleIndex]);
                $rulesPost = array_values($rulesPost);
            }
            return $rulesPost;
        }

        if (strpos($action, 'add_') === 0) {
            $payload = explode(':', substr($action, 4), 2);
            $side = $payload[0] ?? '';
            $ruleIndex = isset($payload[1]) ? (int)$payload[1] : null;
            if ($ruleIndex !== null && isset($rulesPost[$ruleIndex]) && in_array($side, ['source', 'target'], true)) {
                $pickerKey = $side . '_feed_picker';
                $pickedId = (string)($rulesPost[$ruleIndex][$pickerKey] ?? '');
                if ($pickedId !== '') {
                    $idsKey = $side . '_feed_ids';
                    $rulesPost[$ruleIndex][$idsKey] = $this->uniqueIds(array_merge(
                        $rulesPost[$ruleIndex][$idsKey] ?? [],
                        [$pickedId]
                    ));
                }
            }
            return $rulesPost;
        }

        if (strpos($action, 'remove_') === 0) {
            $payload = explode(':', substr($action, 7), 3);
            $side = $payload[0] ?? '';
            $ruleIndex = isset($payload[1]) ? (int)$payload[1] : null;
            $feedId = $payload[2] ?? null;
            if ($ruleIndex !== null && isset($rulesPost[$ruleIndex]) && in_array($side, ['source', 'target'], true) && $feedId !== null) {
                $idsKey = $side . '_feed_ids';
                $currentIds = $rulesPost[$ruleIndex][$idsKey] ?? [];
                if (is_array($currentIds)) {
                    $rulesPost[$ruleIndex][$idsKey] = array_values(array_filter($currentIds, static function ($id) use ($feedId) {
                        return (string)$id !== (string)$feedId;
                    }));
                }
            }
            return $rulesPost;
        }

        return $rulesPost;
    }

    /** @return array<string,mixed> */
    private function blankRulePost(): array
    {
        return [
            'name' => '',
            'enabled' => 1,
            'source_feed_ids' => [],
            'source_feed_picker' => '',
            'target_feed_ids' => [],
            'target_feed_picker' => '',
            'lookback_days' => 14,
            'normalize_whitespace' => 1,
            'normalize_case' => 1,
            'strip_punctuation' => 1,
        ];
    }

    public function onEntryBeforeInsert(FreshRSS_Entry $entry): FreshRSS_Entry
    {
        try {
            $service = $this->service();
            if ($service->hasRules()) {
                $service->onNewEntry($entry);
            }
        } catch (\Throwable $e) {
            Minz_Log::error('Title Dedup: ' . $e->getMessage());
        }
        return $entry;
    }

    public function onUserMaintenance(): void
    {
        try {
            if (!empty($this->getUserConfiguration()[self::BACKFILL_KEY])) {
                return;
            }
            $service = $this->service();
            if ($service->hasRules()) {
                $service->backfill();
            }
            $config = $this->getUserConfiguration();
            $config[self::BACKFILL_KEY] = true;
            $this->setUserConfiguration($config);
        } catch (\Throwable $e) {
            Minz_Log::error('Title Dedup: ' . $e->getMessage());
        }
    }

    private function service(): DedupService
    {
        if ($this->service === null) {
            $config = RuleRepository::parseConfig($this->rulesJson()) ?? ['rules' => []];
            $this->service = new DedupService($config, fn(string $name): array => $this->findFeedIdsByName($name));
        }
        return $this->service;
    }

    /**
     * Rules are per user. Version 0.1 was a system extension and kept them in the
     * system configuration: use those until the user saves the settings once.
     */
    private function rulesJson(): string
    {
        $json = $this->getUserConfiguration()[self::CONFIG_KEY] ?? null;
        if (is_string($json) && $json !== '') {
            return $json;
        }
        $legacy = $this->getSystemConfiguration()[self::CONFIG_KEY] ?? '';
        return is_string($legacy) ? $legacy : '';
    }

    /** @return list<array<string,mixed>> */
    public function currentRules(): array
    {
        $parsed = RuleRepository::parseConfig($this->rulesJson());
        return $parsed['rules'] ?? [];
    }

    /** @return list<array{id:string,name:string,label:string}> */
    public function feedOptions(): array
    {
        if ($this->feedOptionsCache !== null) {
            return $this->feedOptionsCache;
        }

        $feedDao = FreshRSS_Factory::createFeedDao();
        $feeds = [];
        foreach ($feedDao->listFeeds() as $feed) {
            $name = (string)$feed->name(true);
            $feeds[] = [
                'id' => (string)$feed->id(),
                'name' => $name,
                'label' => $name,
            ];
        }

        usort($feeds, static function ($left, $right) {
            return strcasecmp($left['label'], $right['label']);
        });

        $this->feedOptionsCache = $feeds;
        return $this->feedOptionsCache;
    }

    /**
     * @param array<string,mixed> $rule
     * @return list<string>
     */
    public function selectedFeedIds(array $rule, string $prefix): array
    {
        $ids = [];
        $idField = $prefix . '_feed_ids';
        $nameField = $prefix . '_feed_names';

        if (isset($rule[$idField]) && is_array($rule[$idField])) {
            $ids = array_merge($ids, $rule[$idField]);
        }

        if (isset($rule[$nameField]) && is_array($rule[$nameField])) {
            foreach ($rule[$nameField] as $feedName) {
                $ids = array_merge($ids, $this->findFeedIdsByName($feedName));
            }
        }

        return $this->uniqueIds($ids);
    }

    /** @return list<string> */
    private function findFeedIdsByName(string $feedName): array
    {
        $map = $this->feedNameMap();
        return $map[$this->normalizeFeedName($feedName)] ?? [];
    }

    /** @return array<string,list<string>> */
    private function feedNameMap(): array
    {
        if ($this->feedNameMapCache !== null) {
            return $this->feedNameMapCache;
        }

        $map = [];
        foreach ($this->feedOptions() as $feed) {
            $normalizedName = $this->normalizeFeedName($feed['name']);
            if ($normalizedName === '') {
                continue;
            }
            if (!isset($map[$normalizedName])) {
                $map[$normalizedName] = [];
            }
            $map[$normalizedName][] = $feed['id'];
        }

        $this->feedNameMapCache = [];
        foreach ($map as $name => $ids) {
            $this->feedNameMapCache[$name] = $this->uniqueIds($ids);
        }

        return $this->feedNameMapCache;
    }

    private function normalizeFeedName(string $feedName): string
    {
        return mb_strtolower(trim((string)$feedName), 'UTF-8');
    }

    /** @return list<string> */
    private function uniqueIds(mixed $ids): array
    {
        return RuleRepository::normalizeIds($ids);
    }
}
