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
                    <p class="footer-desc">
                        专注于航空摄影作品的分享与交流平台，连接全球航空摄影爱好者，
                        记录每一个精彩的飞行瞬间，探索天空中的无限可能。
                    </p>

                </div>

                <div class="footer-links-container">
                    <h3 class="footer-title">快速链接</h3>
                    <ul class="footer-links">
                        <li><a href="index.php">首页</a></li>
                        <li><a href="all_photos.php">全部作品</a></li>
                        <li><a href="ladder.php">排行榜</a></li>
                        <li><a href="#">关于我们</a></li>
                        <li><a href="#">联系我们</a></li>
                    </ul>
                </div>

                <div class="footer-links-container">
                    <h3 class="footer-title">帮助中心</h3>
                    <ul class="footer-links">
                        <li><a href="../OurRule.pdf">通过规则</a></li>
                        <li><a href="#">常见问题</a></li>
                        <li><a href="#">用户协议</a></li>
                        <li><a href="#">隐私政策</a></li>
                        <li><a href="#">版权说明</a></li>
                    </ul>
                </div>

                <div class="footer-links-container footer-language">
                    <h3 class="footer-title">Language / 语言</h3>
                    <form class="footer-language-form" method="post" action="/set-locale.php">
                        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo h($footerRedirect); ?>">
                        <select id="footer_language_select" name="locale" aria-label="Select language" onchange="this.form.submit()">
                            <?php foreach ($footerLocales as $code => $label): ?>
                                <option value="<?php echo h($code); ?>" <?php echo $footerCurrentLocale === $code ? 'selected' : ''; ?>><?php echo h($label); ?> (<?php echo strtoupper($code); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <small class="footer-desc">选择上方语言后，整站将即时切换。</small>
                </div>
            </div>

            <div class="copyright">
                &copy; 2025-2026 SY Photos - 保留所有权利
            </div>
        </div>
    </footer>
