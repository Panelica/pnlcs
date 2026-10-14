<?php

// The install wizard.
return [
    'migrate_title' => '正在创建数据库表',
    'migrate_intro' => 'PNLCS 正在设置数据库，通常需要一两分钟。请保持此页面打开，完成后会自动继续。',
    'migrate_progress' => '已完成 :done / :total 步',
    'migrate_failed' => '数据库设置已停止：:message',
    'migrate_retry' => '重试',
    'db_connect_failed' => '无法使用这些设置连接到数据库：:message',
    'cron_title' => '最后一步：计划任务',
    'cron_intro' => 'PNLCS 通过一个需要每分钟启动一次的计划任务来发送邮件、续费服务并执行后台工作。请将这一行添加为网站运行用户的 cron 任务：',
    'cron_php' => '如果服务器上的 php 命令启动的是其他 PHP 版本，请改为填写 PHP 8.4 的路径。',
    'cron_note' => '使用 Docker 或 install.sh 安装？那么这一步已经完成。',
];
