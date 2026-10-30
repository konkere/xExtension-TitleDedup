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
    /** @var list<array{id:string,name:string,label:string,group:string}>|null */
    private ?array $feedOptionsCache = null;
    /** @var array<string,list<string>>|null */
    private ?array $feedNameMapCache = null;

    #[\Override]
    public function init(): void
    {
        parent::init();
        $this->registerTranslates();
        // FreshRSS re-initialises extensions for each user during a cron run.
        $this->service = null;

        // Called for new entries only, after the user's filter actions were applied.
        $this->registerHook('entry_before_add', [$this, 'onEntryBeforeAdd']);
        $this->registerHook('freshrss_user_maintenance', [$this, 'onUserMaintenance']);
    }

    #[\Override]
    public function handleConfigureAction(): void
    {
        $this->registerTranslates();
        // FreshRSS forbids inline styles and scripts (CSP), so they live in static files.
        Minz_View::appendStyle($this->getFileUrl('configure.css'));
        Minz_View::appendScript($this->getFileUrl('configure.js'));
        if (!Minz_Request::isPost()) {
            return;
        }

        $rulesPost = $_POST['rules'] ?? null;
        if (is_array($rulesPost)) {
            $rulesPost = $this->applyUiAction($rulesPost, (string)($_POST['rule_action'] ?? ''));
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

        $json = (string)json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== $this->getUserConfigurationString(self::CONFIG_KEY)) {
            $this->setUserConfigurationValue(self::CONFIG_KEY, $json);
            // Rules changed: let the next maintenance run check the whole lookback window once.
            $this->setUserConfigurationValue(self::BACKFILL_KEY, false);
            $this->service = null;
        }
        Minz_Request::setGoodNotification(_t('ext.title_dedup.notify.saved'));
    }

    /**
     * @param array<mixed> $rulesPost
     * @return array<mixed>
     */
    private function applyUiAction(array $rulesPost, string $action): array
    {
        if ($action === 'add_rule') {
            $rulesPost[] = self::blankRule();
        } elseif (str_starts_with($action, 'remove_rule:')) {
            unset($rulesPost[(int)substr($action, strlen('remove_rule:'))]);
        }
        return array_values($rulesPost);
    }

    /** @return array<string,mixed> */
    public static function blankRule(): array
    {
        return [
            'name' => '',
            'enabled' => true,
            'source_feed_ids' => [],
            'target_feed_ids' => [],
            'lookback_days' => 14,
            'normalize_whitespace' => true,
            'normalize_case' => true,
            'strip_punctuation' => true,
        ];
    }

    public function onEntryBeforeAdd(FreshRSS_Entry $entry): FreshRSS_Entry
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
            if ($this->getUserConfigurationBool(self::BACKFILL_KEY) === true) {
                return;
            }
            $service = $this->service();
            if ($service->hasRules()) {
                $service->backfill();
            }
            $this->setUserConfigurationValue(self::BACKFILL_KEY, true);
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
        $json = $this->getUserConfigurationString(self::CONFIG_KEY);
        if ($json !== null && $json !== '') {
            return $json;
        }
        return $this->getSystemConfigurationString(self::CONFIG_KEY) ?? '';
    }

    /** @return list<array<string,mixed>> */
    public function currentRules(): array
    {
        $parsed = RuleRepository::parseConfig($this->rulesJson());
        return $parsed['rules'] ?? [];
    }

    /**
     * Feeds grouped by category, in the user's category order.
     *
     * @return list<array{id:string,name:string,label:string,group:string}>
     */
    public function feedOptions(): array
    {
        if ($this->feedOptionsCache !== null) {
            return $this->feedOptionsCache;
        }

        $feeds = [];
        foreach (FreshRSS_Factory::createCategoryDao()->listSortedCategories(true) as $category) {
            $group = [];
            foreach ($category->feeds() as $feed) {
                $name = $feed->name(true);
                $group[] = [
                    'id' => (string)$feed->id(),
                    'name' => $name,
                    'label' => $name,
                    'group' => $category->name(),
                ];
            }
            usort($group, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));
            array_push($feeds, ...$group);
        }

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
