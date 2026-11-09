<?php
/*
 * Copyright (c) 2025-2026 Yunyun (Wang Yaoze)
 * Licensed under the GNU General Public License, version 3 only, with
 * additional attribution terms in the NOTICE file.
 * Source: https://github.com/NomenID/plugin-quick-central-login
 * Project: https://nomen.caellab.com/
 */

namespace Nomen\Plugin\QuickCentralLogin;

use Nomen\Src\{Csrf, Database, Hooks, Plugins, Response, Session};

/**
 * 快速中央登录：第一方应用（开发者 Id=1）免确认直接发码，回调带 spicallogin 标记。
 * 核心只提供 oauth.authorize.quick 钩子，判定策略全在这里；用户可在 /special/quick-login 关掉。
 */
class Plugin
{
    /** 回调上的快速登录标记；应用侧只判参数在不在，别比对值 */
    private const CALLBACK_FLAG = 'caellab-launch-plan-sister-project-quick-login';

    public static function boot(): void
    {
        Hooks::add('oauth.authorize.quick', [self::class, 'authorizeQuick'], 10);
        Plugins::route('POST', '/special/quick-login', [self::class, 'toggle'], ['auth']);
    }

    public static function specialPage(): string
    {
        $userId = (int)Session::get('user_id');
        if (!$userId) {
            Response::redirect('/login?redirect_to=' . urlencode('/special/quick-login'));
            return '';
        }

        $enabled = self::enabledFor($userId);
        $flash = (string)(Session::getFlash('success') ?: '');
        $error = (string)(Session::getFlash('error') ?: '');

        $radio = static function (string $value, bool $checked, string $label): string {
            return '<label class="factor-option' . ($checked ? ' selected' : '') . '">'
                . '<input type="radio" name="quick_login" value="' . $value . '"' . ($checked ? ' checked' : '') . '>'
                . '<span class="factor-label">' . htmlspecialchars($label) . '</span></label>';
        };

        return ($flash !== '' ? '<p class="form-hint" role="status">' . htmlspecialchars($flash) . '</p>' : '')
            . ($error !== '' ? '<p class="form-hint" role="alert">' . htmlspecialchars($error) . '</p>' : '')
            . '<p class="form-hint">' . htmlspecialchars(t('Apps published by this site sign you in without asking for permission every time, and the callback carries a quick-login flag. Third-party apps always show the consent screen.')) . '</p>'
            . '<p class="form-hint">' . htmlspecialchars(t('Turn quick sign-in off and every app asks for confirmation again.')) . '</p>'
            . '<form method="POST" action="/special/quick-login">'
            . Csrf::field()
            . '<div class="factor-select">'
            . $radio('1', $enabled, t('On'))
            . $radio('0', !$enabled, t('Off'))
            . '</div>'
            . '<p class="form-hint">' . htmlspecialchars(t($enabled ? 'Quick sign-in is on' : 'Quick sign-in is off')) . '</p>'
            . '<button type="submit" class="btn btn-primary">' . htmlspecialchars(t('Save')) . '</button>'
            . '</form>';
    }

    public static function toggle(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Session::flash('error', t('Invalid request, try again'));
            Response::redirect('/special/quick-login');
            return;
        }

        $userId = (int)Session::get('user_id');
        $enabled = ($_POST['quick_login'] ?? '1') === '1';
        $db = Database::getInstance();

        if ($enabled) {
            // 没有记录就是默认开启，不为「开」攒行
            $db->prepare('DELETE FROM quick_login_prefs WHERE user_id = :id')->execute([':id' => $userId]);
        } else {
            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare('SELECT user_id FROM quick_login_prefs WHERE user_id = :id');
            $stmt->execute([':id' => $userId]);
            if ($stmt->fetchColumn()) {
                $db->prepare('UPDATE quick_login_prefs SET enabled = 0, updated_at = :now WHERE user_id = :id')
                    ->execute([':id' => $userId, ':now' => $now]);
            } else {
                $db->prepare('INSERT INTO quick_login_prefs (user_id, enabled, updated_at) VALUES (:id, 0, :now)')
                    ->execute([':id' => $userId, ':now' => $now]);
            }
        }

        Session::flash('success', t($enabled ? 'Quick sign-in is on' : 'Quick sign-in is off'));
        Response::redirect('/special/quick-login');
    }

    /**
     * oauth.authorize.quick 钩子：返回非空字符串 = 免确认直发，回调带上这个标记。
     */
    public static function authorizeQuick(mixed $flag, int $userId, array $client, array $scopes): ?string
    {
        if ((int)($client['developer_id'] ?? 0) !== 1) {
            return null;
        }

        return self::enabledFor($userId) ? self::CALLBACK_FLAG : null;
    }

    private static function enabledFor(int $userId): bool
    {
        $stmt = Database::getInstance()->prepare('SELECT enabled FROM quick_login_prefs WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();

        return !$row || (int)$row['enabled'] === 1;
    }
}
