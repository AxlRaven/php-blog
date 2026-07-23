</main>
<script>
  window.BLOG_UPLOAD_URL = <?= json_encode(url('admin/upload'), JSON_UNESCAPED_UNICODE) ?>;
  window.BLOG_CSRF = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php if (!empty($adminWithEditor)): ?>
<script src="<?= e(asset('js/prism.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-markup.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-css.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-javascript.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-php.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-python.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-sql.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-bash.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-json.min.js')) ?>"></script>
<?php endif; ?>
<script src="<?= e(asset('js/easymde.min.js')) ?>"></script>
<script src="<?= e(asset('js/admin.js')) ?>"></script>
</body>
</html>
