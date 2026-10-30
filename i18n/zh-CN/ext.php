<?php

return [
	'title_dedup' => [
		'intro' => '用于按标题把目标源中已在参考源出现过的文章自动标记为已读。',
		'sidebar_hint' => '侧边栏内编辑时会自动关闭，请点击右侧按钮，在完整页面中编辑。',
		'open_full' => '在完整页面中编辑',
		'backfill_hint' => '保存后，下一次刷新会对回看天数内的已有文章检查一次。此后只检查新文章，手动标为未读的文章将保持未读。',
		'rule' => [
			'name' => '规则名',
			'name_placeholder' => '例如：36Kr-AI 去重',
			'enabled' => '启用',
			'remove' => '删除规则组',
			'add' => '新增规则组',
		],
		'source' => [
			'title' => '参考源',
			'help' => '这些源里出现过的同标题文章，会作为去重依据',
		],
		'target' => [
			'title' => '目标源',
			'help' => '命中的文章会自动已读',
		],
		'picker' => [
			'choose' => '选择订阅…',
			'add' => '添加',
			'remove' => '移除',
		],
		'lookback' => '回看天数',
		'normalize' => [
			'title' => '归一化',
			'whitespace' => '压缩空白',
			'case' => '忽略大小写',
			'punctuation' => '删除标点符号',
		],
		'save' => '保存',
		'notify' => [
			'saved' => '去重规则已保存。',
			'invalid' => '规则配置无效，未保存。',
		],
	],
];
