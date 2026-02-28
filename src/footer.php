<?php
if (!function_exists('h')) {
    require_once __DIR__ . '/helpers.php';
}
if (!function_exists('available_locales')) {
    require_once __DIR__ . '/i18n.php';
}
$footerLocales = available_locales();
$footerCurrentLocale = current_locale();
$footerRedirect = $_SERVER['REQUEST_URI'] ?? '/';
?>
    <!-- 页脚 -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-about">
                    <div class="footer-logo">
                        <img src="8.jpg" alt="SY Photos">
                        <span>SY Photos</span>
                    </div>
                    <p class="footer-desc"><?php echo h(t('footer_desc')); ?></p>

                </div>

                <div class="footer-links-container">
                    <h3 class="footer-title"><?php echo h(t('footer_quick_links')); ?></h3>
                    <ul class="footer-links">
                        <li><a href="index.php"><?php echo h(t('footer_home')); ?></a></li>
                        <li><a href="all_photos.php"><?php echo h(t('footer_all_photos')); ?></a></li>
                        <li><a href="ladder.php"><?php echo h(t('footer_ladder')); ?></a></li>
                        <li><a href="#"><?php echo h(t('footer_about_link')); ?></a></li>
                        <li><a href="#"><?php echo h(t('footer_contact_link')); ?></a></li>
                    </ul>
                </div>

                <div class="footer-links-container">
                    <h3 class="footer-title"><?php echo h(t('footer_help_center')); ?></h3>
                    <ul class="footer-links">
                        <li><a href="../OurRule.pdf"><?php echo h(t('footer_rule')); ?></a></li>
                        <li><a href="#"><?php echo h(t('footer_faq')); ?></a></li>
                        <li><a href="#"><?php echo h(t('footer_terms')); ?></a></li>
                        <li><a href="#"><?php echo h(t('footer_privacy')); ?></a></li>
                        <li><a href="#"><?php echo h(t('footer_copyright')); ?></a></li>
                    </ul>
                </div>

                <div class="footer-links-container footer-language">
                    <h3 class="footer-title"><?php echo h(t('footer_language_title')); ?></h3>
                    <form class="footer-language-form" method="post" action="/set-locale.php">
                        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo h($footerRedirect); ?>">
                        <select id="footer_language_select" name="locale" aria-label="Select language" onchange="this.form.submit()">
                            <?php foreach ($footerLocales as $code => $label): ?>
                                <option value="<?php echo h($code); ?>" <?php echo $footerCurrentLocale === $code ? 'selected' : ''; ?>><?php echo h($label); ?> (<?php echo strtoupper($code); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <small class="footer-desc"><?php echo h(t('footer_language_hint')); ?></small>
                </div>
            </div>

            <div class="copyright">
                &copy; 2025-2026 SY Photos - <?php echo h(t('footer_copyright_notice')); ?>
            </div>
        </div>
    </footer>

