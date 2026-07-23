<?php
declare(strict_types=1);

function auth_user(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    static $user = false;
    if ($user === false) {
        $stmt = db()->prepare('SELECT id, username, totp_secret FROM admins WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['admin_id']]);
        $user = $stmt->fetch() ?: null;
        if (!$user) {
            unset($_SESSION['admin_id']);
        }
    }
    return $user;
}

function require_auth(): void
{
    if (totp_login_pending()) {
        flash('error', 'Подтвердите код двухфакторной аутентификации.');
        redirect(url('admin/login-2fa'));
    }
    if (!auth_user()) {
        flash('error', 'Войдите в аккаунт администратора.');
        redirect(url('admin/login'));
    }
}

function admin_has_totp(?int $adminId = null): bool
{
    $id = $adminId ?? (int) ($_SESSION['admin_id'] ?? 0);
    if ($id < 1) {
        return false;
    }
    $stmt = db()->prepare('SELECT totp_secret FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $secret = $stmt->fetchColumn();

    return is_string($secret) && $secret !== '';
}

function totp_login_pending(): bool
{
    if (empty($_SESSION['pending_2fa_admin_id'])) {
        return false;
    }
    $expires = (int) ($_SESSION['pending_2fa_expires'] ?? 0);
    if ($expires > 0 && $expires < time()) {
        clear_pending_login();
        return false;
    }

    return true;
}

function clear_pending_login(): void
{
    unset($_SESSION['pending_2fa_admin_id'], $_SESSION['pending_2fa_expires']);
}

/**
 * @return true|'totp_required'|false
 */
function attempt_login(string $username, string $password): bool|string
{
    $stmt = db()->prepare('SELECT id, username, password_hash, totp_secret FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    clear_pending_login();
    unset($_SESSION['admin_id']);

    if (is_string($row['totp_secret'] ?? null) && $row['totp_secret'] !== '') {
        $_SESSION['pending_2fa_admin_id'] = (int) $row['id'];
        $_SESSION['pending_2fa_expires'] = time() + 300;

        return 'totp_required';
    }

    $_SESSION['admin_id'] = (int) $row['id'];

    return true;
}

function complete_totp_login(string $code): bool
{
    if (!totp_login_pending()) {
        return false;
    }

    $adminId = (int) $_SESSION['pending_2fa_admin_id'];
    $stmt = db()->prepare('SELECT id, totp_secret FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$adminId]);
    $row = $stmt->fetch();
    if (!$row || !is_string($row['totp_secret'] ?? null) || $row['totp_secret'] === '') {
        clear_pending_login();
        return false;
    }

    if (!totp_verify($row['totp_secret'], $code)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = $adminId;
    clear_pending_login();

    return true;
}

function enable_admin_totp(int $adminId, string $secret): void
{
    $stmt = db()->prepare('UPDATE admins SET totp_secret = ? WHERE id = ?');
    $stmt->execute([$secret, $adminId]);
}

function disable_admin_totp(int $adminId): void
{
    $stmt = db()->prepare('UPDATE admins SET totp_secret = NULL WHERE id = ?');
    $stmt->execute([$adminId]);
}

function logout(): void
{
    unset($_SESSION['admin_id']);
    clear_pending_login();
    unset($_SESSION['totp_setup_secret']);
    session_regenerate_id(true);
}
