</main>
    <footer class="site-footer">
        <div class="site-footer__inner">
            <p>&copy; <?= date('Y') ?> · <?= e(site_title()) ?> · <a href="<?= e(absolute_url('feed')) ?>">RSS</a></p>
        </div>
    </footer>
</div>
<script src="<?= e(asset('js/prism.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-markup.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-css.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-javascript.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-php.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-python.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-sql.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-bash.min.js')) ?>"></script>
<script src="<?= e(asset('js/prism-json.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
