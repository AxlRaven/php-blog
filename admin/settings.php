<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_auth();

$user = auth_user();
$totpEnabled = admin_has_totp((int) $user['id']);
$totpSetupSecret = (string) ($_SESSION['totp_setup_secret'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'totp_begin') {
        if ($totpEnabled) {
            flash('error', '2FA уже включена.');
            redirect(url('admin/settings'));
        }
        $_SESSION['totp_setup_secret'] = totp_generate_secret();
        flash('success', 'Отсканируйте QR-код или введите ключ вручную, затем подтвердите кодом.');
        redirect(url('admin/settings'));
    }

    if ($action === 'totp_cancel') {
        unset($_SESSION['totp_setup_secret']);
        flash('success', 'Настройка 2FA отменена.');
        redirect(url('admin/settings'));
    }

    if ($action === 'totp_confirm') {
        $code = trim((string) ($_POST['totp_code'] ?? ''));
        $pending = (string) ($_SESSION['totp_setup_secret'] ?? '');
        if ($pending === '') {
            flash('error', 'Сначала начните настройку 2FA.');
            redirect(url('admin/settings'));
        }
        if (!totp_verify($pending, $code)) {
            flash('error', 'Неверный код. Проверьте приложение и попробуйте снова.');
            redirect(url('admin/settings'));
        }
        enable_admin_totp((int) $user['id'], $pending);
        unset($_SESSION['totp_setup_secret']);
        flash('success', 'Двухфакторная аутентификация включена.');
        redirect(url('admin/settings'));
    }

    if ($action === 'totp_disable') {
        $password = (string) ($_POST['totp_disable_password'] ?? '');
        $code = trim((string) ($_POST['totp_disable_code'] ?? ''));
        $stmt = db()->prepare('SELECT password_hash, totp_secret FROM admins WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $user['id']]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, $row['password_hash'])) {
            flash('error', 'Неверный пароль.');
            redirect(url('admin/settings'));
        }
        $secret = (string) ($row['totp_secret'] ?? '');
        if ($secret === '' || !totp_verify($secret, $code)) {
            flash('error', 'Неверный код TOTP.');
            redirect(url('admin/settings'));
        }
        disable_admin_totp((int) $user['id']);
        unset($_SESSION['totp_setup_secret']);
        flash('success', 'Двухфакторная аутентификация отключена.');
        redirect(url('admin/settings'));
    }

    if ($action !== '' && $action !== 'save') {
        redirect(url('admin/settings'));
    }

    $siteTitle = trim((string) ($_POST['site_title'] ?? ''));
    $siteDesc = trim((string) ($_POST['site_description'] ?? ''));
    $siteUrl = trim((string) ($_POST['site_url'] ?? ''));
    $siteKeywords = trim((string) ($_POST['site_keywords'] ?? ''));
    $seoAuthor = trim((string) ($_POST['seo_author'] ?? ''));
    $seoOrganization = trim((string) ($_POST['seo_organization'] ?? ''));
    $seoTwitter = trim((string) ($_POST['seo_twitter'] ?? ''));
    $seoOgImage = trim((string) ($_POST['seo_og_image'] ?? ''));
    $googleVerification = trim((string) ($_POST['google_site_verification'] ?? ''));
    $yandexVerification = trim((string) ($_POST['yandex_verification'] ?? ''));
    $perPage = max(1, min(100, (int) ($_POST['posts_per_page'] ?? 10)));
    $loadMode = ($_POST['load_mode'] ?? 'pagination') === 'infinite' ? 'infinite' : 'pagination';

    if ($siteTitle === '') {
        flash('error', 'Название сайта не может быть пустым.');
        redirect(url('admin/settings'));
    }
    if ($siteUrl !== '' && !preg_match('#^https?://#i', $siteUrl)) {
        flash('error', 'URL сайта должен начинаться с http:// или https://');
        redirect(url('admin/settings'));
    }

    $brandingNotes = [];
    $coverPathRel = '/uploads/images/cover.jpg';

    if (!empty($_POST['remove_site_logo'])) {
        delete_branding_file('logo.png');
        $brandingNotes[] = 'логотип удалён';
    }
    if (!empty($_FILES['site_logo']['tmp_name']) && (int) ($_FILES['site_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $logoErr = save_branding_upload($_FILES['site_logo'], 'logo.png');
        if ($logoErr !== null) {
            flash('error', 'Логотип: ' . $logoErr);
            redirect(url('admin/settings'));
        }
        $brandingNotes[] = 'логотип загружен';
    }

    if (!empty($_POST['remove_site_cover'])) {
        delete_branding_file('cover.jpg');
        if ($seoOgImage === '' || $seoOgImage === $coverPathRel || str_ends_with($seoOgImage, '/uploads/images/cover.jpg')) {
            $seoOgImage = '';
        }
        $brandingNotes[] = 'cover удалён';
    }
    if (!empty($_FILES['site_cover']['tmp_name']) && (int) ($_FILES['site_cover']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $coverErr = save_branding_upload($_FILES['site_cover'], 'cover.jpg');
        if ($coverErr !== null) {
            flash('error', 'Cover: ' . $coverErr);
            redirect(url('admin/settings'));
        }
        if ($seoOgImage === '' || str_ends_with($seoOgImage, '/uploads/images/cover.jpg')) {
            $seoOgImage = $coverPathRel;
        }
        $brandingNotes[] = 'cover загружен';
    }

    set_setting('site_title', $siteTitle);
    set_setting('site_description', $siteDesc);
    set_setting('site_url', rtrim($siteUrl, '/'));
    set_setting('site_keywords', $siteKeywords);
    set_setting('seo_author', $seoAuthor);
    set_setting('seo_organization', $seoOrganization);
    set_setting('seo_twitter', ltrim($seoTwitter, '@'));
    set_setting('seo_og_image', $seoOgImage);
    set_setting('google_site_verification', $googleVerification);
    set_setting('yandex_verification', $yandexVerification);
    set_setting('posts_per_page', (string) $perPage);
    set_setting('load_mode', $loadMode);

    $newPass = (string) ($_POST['new_password'] ?? '');
    $newPass2 = (string) ($_POST['new_password2'] ?? '');
    if ($newPass !== '' || $newPass2 !== '') {
        if (strlen($newPass) < 6) {
            flash('error', 'Новый пароль должен быть не короче 6 символов.');
            redirect(url('admin/settings'));
        }
        if ($newPass !== $newPass2) {
            flash('error', 'Пароли не совпадают.');
            redirect(url('admin/settings'));
        }
        $user = auth_user();
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
            ->execute([$hash, (int) $user['id']]);
        $msg = 'Настройки и пароль сохранены.';
    } else {
        $msg = 'Настройки сохранены.';
    }
    if ($brandingNotes) {
        $msg .= ' (' . implode(', ', $brandingNotes) . ')';
    }
    flash('success', $msg);

    redirect(url('admin/settings'));
}

$totpQrDataUri = '';
if ($totpSetupSecret !== '') {
    $totpQrDataUri = totp_qr_data_uri($totpSetupSecret, (string) $user['username']) ?? '';
}

$logoUrl = site_logo_url();
$coverUrl = site_cover_url();

$adminTitle = 'Настройки';
require __DIR__ . '/_header.php';
?>

<div class="admin-page-head">
    <h1>Настройки</h1>
</div>

<form method="post" class="settings-form" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <section class="admin-panel">
        <h2>Сайт</h2>
        <div class="form-row">
            <label for="site_title">Название</label>
            <input id="site_title" name="site_title" required value="<?= e(site_title()) ?>">
        </div>
        <div class="form-row">
            <label for="site_description">Описание</label>
            <input id="site_description" name="site_description" value="<?= e(site_description()) ?>">
            <p class="field-hint">Краткое описание для поисковиков и соцсетей (до 160 символов).</p>
        </div>
    </section>

    <section class="admin-panel">
        <h2>Логотип и обложка</h2>
        <p class="field-hint">Файлы сохраняются в <code>uploads/images/</code> (нужны права на запись). JPEG, PNG, GIF или WebP, до 5 МБ.</p>

        <div class="branding-grid">
            <div class="branding-card">
                <h3 class="branding-card__title">Логотип</h3>
                <div class="branding-card__preview branding-card__preview--logo">
                    <?php if ($logoUrl): ?>
                        <img src="<?= e($logoUrl) ?>" alt="Логотип" width="72" height="72">
                    <?php else: ?>
                        <span class="branding-card__empty">Нет файла</span>
                    <?php endif; ?>
                </div>
                <div class="form-row">
                    <label for="site_logo">Загрузить logo.png</label>
                    <input id="site_logo" name="site_logo" type="file" accept="image/png,image/jpeg,image/gif,image/webp">
                    <p class="field-hint">Квадрат 256×256 px рекомендуется. Показывается в шапке и как favicon.</p>
                </div>
                <?php if ($logoUrl): ?>
                    <label class="check">
                        <input type="checkbox" name="remove_site_logo" value="1">
                        <span>Удалить текущий логотип</span>
                    </label>
                <?php endif; ?>
            </div>

            <div class="branding-card">
                <h3 class="branding-card__title">Cover (OG)</h3>
                <div class="branding-card__preview branding-card__preview--cover">
                    <?php if ($coverUrl): ?>
                        <img src="<?= e($coverUrl) ?>" alt="Cover">
                    <?php else: ?>
                        <span class="branding-card__empty">Нет файла</span>
                    <?php endif; ?>
                </div>
                <div class="form-row">
                    <label for="site_cover">Загрузить cover.jpg</label>
                    <input id="site_cover" name="site_cover" type="file" accept="image/png,image/jpeg,image/gif,image/webp">
                    <p class="field-hint">Картинка по умолчанию для соцсетей. Сохраняется как <code>uploads/images/cover.jpg</code>.</p>
                </div>
                <?php if ($coverUrl): ?>
                    <label class="check">
                        <input type="checkbox" name="remove_site_cover" value="1">
                        <span>Удалить текущий cover</span>
                    </label>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="admin-panel">
        <h2>SEO</h2>
        <div class="form-row">
            <label for="site_url">URL сайта (канонический)</label>
            <input id="site_url" name="site_url" type="url" placeholder="https://life.voronin.one" value="<?= e(setting('site_url', '') ?? '') ?>">
            <p class="field-hint">Полный адрес с https:// — для sitemap, Open Graph и canonical. Если пусто, определяется автоматически.</p>
        </div>
        <div class="form-row">
            <label for="site_keywords">Ключевые слова</label>
            <input id="site_keywords" name="site_keywords" value="<?= e(setting('site_keywords', '') ?? '') ?>" placeholder="блог, php, жизнь, заметки">
            <p class="field-hint">Через запятую. Добавляются к ключевым словам записей (тегам).</p>
        </div>
        <div class="form-row form-row--split">
            <div>
                <label for="seo_author">Автор</label>
                <input id="seo_author" name="seo_author" value="<?= e(setting('seo_author', '') ?? '') ?>">
            </div>
            <div>
                <label for="seo_organization">Организация / издатель</label>
                <input id="seo_organization" name="seo_organization" value="<?= e(setting('seo_organization', '') ?? '') ?>">
            </div>
        </div>
        <div class="form-row form-row--split">
            <div>
                <label for="seo_twitter">Twitter / X</label>
                <input id="seo_twitter" name="seo_twitter" value="<?= e(setting('seo_twitter', '') ?? '') ?>" placeholder="@username">
            </div>
            <div>
                <label for="seo_og_image">Картинка по умолчанию (OG)</label>
                <input id="seo_og_image" name="seo_og_image" value="<?= e(setting('seo_og_image', '') ?? '') ?>" placeholder="/uploads/images/cover.jpg">
                <p class="field-hint">Путь или полный URL. При загрузке cover.jpg подставляется автоматически, если поле пустое.</p>
            </div>
        </div>
        <div class="form-row form-row--split">
            <div>
                <label for="google_site_verification">Google Search Console</label>
                <input id="google_site_verification" name="google_site_verification" value="<?= e(setting('google_site_verification', '') ?? '') ?>">
            </div>
            <div>
                <label for="yandex_verification">Яндекс Вебмастер</label>
                <input id="yandex_verification" name="yandex_verification" value="<?= e(setting('yandex_verification', '') ?? '') ?>">
            </div>
        </div>
        <p class="field-hint">RSS: <a href="<?= e(absolute_url('feed')) ?>" target="_blank" rel="noopener"><?= e(absolute_url('feed')) ?></a> · Sitemap: <a href="<?= e(absolute_url('sitemap.xml')) ?>" target="_blank" rel="noopener"><?= e(absolute_url('sitemap.xml')) ?></a> · Robots: <a href="<?= e(absolute_url('robots.txt')) ?>" target="_blank" rel="noopener"><?= e(absolute_url('robots.txt')) ?></a></p>
        <p class="field-hint">Telegram кэширует превью. После изменений отправьте ссылку боту <a href="https://t.me/WebpageBot" target="_blank" rel="noopener">@WebpageBot</a>, чтобы обновить карточку.</p>
    </section>

    <section class="admin-panel">
        <h2>Лента</h2>
        <div class="form-row">
            <label for="posts_per_page">Записей на странице</label>
            <input id="posts_per_page" name="posts_per_page" type="number" min="1" max="100" value="<?= (int) posts_per_page() ?>">
        </div>
        <div class="form-row">
            <label>Подгрузка следующих записей</label>
            <div class="radio-list">
                <label class="radio">
                    <input type="radio" name="load_mode" value="pagination" <?= load_mode() === 'pagination' ? 'checked' : '' ?>>
                    <span>Постранично (номера страниц)</span>
                </label>
                <label class="radio">
                    <input type="radio" name="load_mode" value="infinite" <?= load_mode() === 'infinite' ? 'checked' : '' ?>>
                    <span>Добавлять снизу в ленту (кнопка «Показать ещё»)</span>
                </label>
            </div>
        </div>
    </section>

    <section class="admin-panel">
        <h2>Двухфакторная аутентификация (TOTP)</h2>
        <p class="field-hint">Google Authenticator, Authy, 1Password и другие приложения с поддержкой TOTP.</p>

        <?php if ($totpEnabled): ?>
            <p class="totp-status totp-status--on"><strong>2FA включена</strong> — при входе потребуется код из приложения.</p>
            <div class="form-row form-row--split">
                <div>
                    <label for="totp_disable_password">Пароль</label>
                    <input id="totp_disable_password" name="totp_disable_password" type="password" autocomplete="current-password">
                </div>
                <div>
                    <label for="totp_disable_code">Код TOTP</label>
                    <input id="totp_disable_code" name="totp_disable_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000">
                </div>
            </div>
            <div class="form-actions totp-form__actions">
                <button class="btn btn--ghost btn--danger" type="submit" name="action" value="totp_disable">Отключить 2FA</button>
            </div>
        <?php elseif ($totpSetupSecret !== ''): ?>
            <p class="totp-status totp-status--setup">Отсканируйте QR-код в приложении-аутентификаторе и введите полученный код.</p>
            <div class="totp-setup">
                <div class="totp-setup__qr">
                    <?php if ($totpQrDataUri !== ''): ?>
                        <img src="<?= e($totpQrDataUri) ?>" width="220" height="220" alt="QR-код для настройки 2FA">
                    <?php else: ?>
                        <p class="totp-setup__qr-error">Не удалось создать QR-код. Убедитесь, что на сервере включено расширение GD.</p>
                    <?php endif; ?>
                </div>
                <div class="totp-setup__manual">
                    <p class="field-hint">Или введите ключ вручную:</p>
                    <code class="totp-secret" id="totp-secret-key"><?= e(totp_format_secret($totpSetupSecret)) ?></code>
                    <button type="button" class="btn btn--ghost btn--small" id="totp-copy-secret">Скопировать ключ</button>
                </div>
            </div>
            <div class="form-row">
                <label for="totp_code">Код из приложения</label>
                <input id="totp_code" name="totp_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000">
            </div>
            <div class="form-actions totp-form__actions">
                <button class="btn" type="submit" name="action" value="totp_confirm">Подтвердить и включить</button>
                <button class="btn btn--ghost" type="submit" name="action" value="totp_cancel">Отмена</button>
            </div>
            <script>
            (function () {
                var btn = document.getElementById('totp-copy-secret');
                var key = document.getElementById('totp-secret-key');
                if (!btn || !key) return;
                btn.addEventListener('click', function () {
                    var text = (key.textContent || '').replace(/\s+/g, '');
                    if (!text) return;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function () {
                            btn.textContent = 'Скопировано';
                            setTimeout(function () { btn.textContent = 'Скопировать ключ'; }, 2000);
                        });
                    }
                });
            })();
            </script>
        <?php else: ?>
            <p class="totp-status totp-status--off">2FA не настроена.</p>
            <div class="form-actions totp-form__actions">
                <button class="btn" type="submit" name="action" value="totp_begin">Настроить 2FA</button>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-panel">
        <h2>Смена пароля</h2>
        <p class="muted">Оставьте пустым, если менять не нужно.</p>
        <div class="form-row form-row--split">
            <div>
                <label for="new_password">Новый пароль</label>
                <input id="new_password" name="new_password" type="password" autocomplete="new-password">
            </div>
            <div>
                <label for="new_password2">Повтор</label>
                <input id="new_password2" name="new_password2" type="password" autocomplete="new-password">
            </div>
        </div>
    </section>

    <div class="form-actions">
        <button class="btn" type="submit" name="action" value="save">Сохранить</button>
    </div>
</form>

<?php require __DIR__ . '/_footer.php'; ?>
