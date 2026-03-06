<?php
require 'db_connect.php';
require 'src/photo_feed_service.php';
session_start();

$filters = photo_feed_normalize_filters($_GET);
$iataCode = $filters['iatacode'];
$userId = $filters['user_id'];
$airline = $filters['airline'];
$aircraftModel = $filters['aircraft_model'];
$cam = $filters['cam'];
$lens = $filters['lens'];
$registrationNumber = $filters['registration_number'];
$keyword = $filters['keyword'];
$page = $filters['page'];
$selectedUser = null;
$userDisplayName = '';
$initialSuggestions = [];

$photos = [];
$errorMessage = '';
$totalPhotos = 0;
$hasMore = false;

try {
    if ($userId > 0) {
        $selectedUser = photo_feed_fetch_user_basic($pdo, $userId);
        $userDisplayName = $selectedUser['username'] ?? '';
    }
    $totalPhotos = photo_feed_fetch_total($pdo, $filters);
    $photos = photo_feed_fetch_page($pdo, $filters);
    $offset = ($filters['page'] - 1) * $filters['per_page'];
    $hasMore = ($offset + count($photos)) < $totalPhotos;
} catch (PDOException $e) {
    $errorMessage = '获取图片失败: ' . $e->getMessage();
}

try {
    $initialSuggestions = [
        'userid' => photo_feed_fetch_filter_suggestions($pdo, 'userid', $userDisplayName, $filters, 10),
        'airline' => photo_feed_fetch_filter_suggestions($pdo, 'airline', $airline, $filters, 10),
        'aircraft_model' => photo_feed_fetch_filter_suggestions($pdo, 'aircraft_model', $aircraftModel, $filters, 10),
        'cam' => photo_feed_fetch_filter_suggestions($pdo, 'cam', $cam, $filters, 10),
        'lens' => photo_feed_fetch_filter_suggestions($pdo, 'lens', $lens, $filters, 10),
        'registration_number' => photo_feed_fetch_filter_suggestions($pdo, 'registration_number', $registrationNumber, $filters, 10),
        'iatacode' => photo_feed_fetch_filter_suggestions($pdo, 'iatacode', $iataCode, $filters, 10),
    ];
} catch (PDOException $e) {
    if ($errorMessage === '') {
        $errorMessage = '获取筛选项失败: ' . $e->getMessage();
    }
}

$pageTitleParts = ['SY Photos 图库'];
if ($iataCode !== '') {
    $pageTitleParts[] = $iataCode;
}
if ($userId > 0) {
    $pageTitleParts[] = $userDisplayName !== '' ? $userDisplayName : ('用户 ' . $userId);
}
$filterSummaryParts = [];
if ($airline !== '') {
    $pageTitleParts[] = '航司 ' . $airline;
    $filterSummaryParts[] = '航司 ' . $airline;
}
if ($aircraftModel !== '') {
    $pageTitleParts[] = '机型 ' . $aircraftModel;
    $filterSummaryParts[] = '机型 ' . $aircraftModel;
}
if ($cam !== '') {
    $pageTitleParts[] = '相机 ' . $cam;
    $filterSummaryParts[] = '相机 ' . $cam;
}
if ($lens !== '') {
    $pageTitleParts[] = '镜头 ' . $lens;
    $filterSummaryParts[] = '镜头 ' . $lens;
}
if ($registrationNumber !== '') {
    $pageTitleParts[] = '序列号 ' . $registrationNumber;
    $filterSummaryParts[] = '序列号 ' . $registrationNumber;
}
if ($iataCode !== '') {
    $filterSummaryParts[] = '拍摄地点 ' . $iataCode;
}
if ($keyword !== '') {
    $pageTitleParts[] = '搜索 ' . $keyword;
    $filterSummaryParts[] = '关键字 ' . $keyword;
}
if ($userId > 0) {
    $filterSummaryParts[] = '作者 ' . ($userDisplayName !== '' ? $userDisplayName : ('用户 ' . $userId));
}
$pageTitle = implode(' - ', $pageTitleParts);
$apiAccess = photo_feed_issue_access_signature($filters);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        .photolist-page {
            background: #f3f6fb;
            min-height: 100vh;
        }

        .photolist-header {
            padding: 18px 16px 14px;
            background: #ffffff;
            border-bottom: 1px solid #e6ebf2;
        }

        .photolist-filter-wrap {
            padding: 16px;
            display: none;
        }

        .photolist-filter-panel {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(22, 93, 255, 0.08);
            padding: 16px;
        }

        .photolist-filter-title {
            margin: 0 0 12px;
            font-size: 1rem;
            color: #1d2129;
        }

        .photolist-filter-grid {
            display: grid;
            gap: 12px;
        }

        .photolist-filter-group label {
            display: block;
            margin-bottom: 6px;
            color: #4e5969;
            font-size: 0.88rem;
            font-weight: 600;
        }

        .photolist-filter-group input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #cfe0fb;
            border-radius: 10px;
            font-size: 0.95rem;
            background: #f8fbff;
        }

        .photolist-filter-group input:focus {
            outline: none;
            border-color: #165dff;
            background: #ffffff;
        }

        .photolist-suggestions {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .photolist-suggestion {
            border: 0;
            background: #e8f3ff;
            color: #165dff;
            border-radius: 999px;
            padding: 7px 10px;
            font-size: 0.82rem;
            cursor: pointer;
        }

        .photolist-filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
        }

        .photolist-filter-submit,
        .photolist-filter-reset {
            flex: 1;
            border: 0;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.94rem;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
        }

        .photolist-filter-submit {
            background: #165dff;
            color: #ffffff;
        }

        .photolist-filter-reset {
            background: #eef4ff;
            color: #165dff;
        }

        .photolist-filter-fab {
            position: fixed;
            right: 16px;
            bottom: 84px;
            width: 56px;
            height: 56px;
            border: 0;
            border-radius: 50%;
            background: #165dff;
            color: #ffffff;
            box-shadow: 0 12px 24px rgba(22, 93, 255, 0.25);
            font-size: 1.2rem;
            z-index: 130;
        }

        .photolist-filter-modal {
            position: fixed;
            inset: 0;
            background: rgba(13, 25, 48, 0.42);
            display: flex;
            align-items: flex-end;
            z-index: 140;
        }

        .photolist-filter-modal[hidden] {
            display: none;
        }

        .photolist-filter-sheet {
            width: 100%;
            max-height: 84vh;
            overflow-y: auto;
            background: #ffffff;
            border-radius: 20px 20px 0 0;
            padding: 18px 16px 22px;
        }

        .photolist-filter-sheet-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .photolist-filter-close {
            border: 0;
            background: #eef4ff;
            color: #165dff;
            border-radius: 999px;
            padding: 8px 12px;
            font-weight: 700;
        }

        .photolist-title {
            margin: 0;
            font-size: 1.15rem;
            color: #1d2129;
        }

        .photolist-meta {
            margin-top: 6px;
            color: #4e5969;
            font-size: 0.92rem;
        }

        .photolist-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
            align-items: start;
            background: #dfeeff;
        }

        .photolist-card {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            aspect-ratio: 16 / 9;
            overflow: hidden;
            background: #dfeeff;
        }

        .photolist-card img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
            transition: transform 0.25s ease;
        }

        .photolist-card:hover img {
            transform: scale(1.02);
        }

        .photolist-empty,
        .photolist-error,
        .photolist-loading {
            margin: 20px 16px;
            padding: 14px 16px;
            border-radius: 10px;
            background: #ffffff;
            color: #4e5969;
            text-align: center;
        }

        .photolist-error {
            color: #b42318;
            background: #fff1f3;
        }

        .photolist-loading {
            margin-top: 12px;
            margin-bottom: 0;
        }

        .photolist-loading[hidden] {
            display: none;
        }

        .photolist-action {
            display: block;
            width: calc(100% - 32px);
            margin: 12px 16px 0;
            padding: 14px 16px;
            border: 0;
            border-radius: 10px;
            background: #165dff;
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .photolist-action:hover {
            background: #0e42d2;
            transform: translateY(-1px);
        }

        .photolist-action.is-end {
            background: #7aa7e8;
        }

        .photolist-sentinel {
            height: 1px;
        }

        @media (min-width: 768px) {
            .photolist-header {
                padding: 24px 24px 18px;
            }

            .photolist-grid {
                grid-template-columns: repeat(5, 1fr);
            }

            .photolist-card {
                aspect-ratio: 16 / 9;
            }

            .photolist-filter-wrap {
                padding: 20px 24px 0;
                display: block;
            }

            .photolist-filter-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .photolist-filter-fab,
            .photolist-filter-modal {
                display: none;
            }
        }

        @media (min-width: 1200px) {
            .photolist-grid {
                grid-template-columns: repeat(6, 1fr);
            }

            .photolist-filter-grid {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/src/nav.php'; ?>

    <main class="photolist-page">
        <section class="photolist-header">
            <h1 class="photolist-title"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="photolist-meta">
                共 <?php echo $totalPhotos; ?> 张图片
                <?php if (!empty($filterSummaryParts)): ?>
                    ，<?php echo htmlspecialchars(implode(' / ', $filterSummaryParts), ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="photolist-filter-wrap">
            <form class="photolist-filter-panel" id="desktopFilterForm" method="get" action="photolist.php">
                <h2 class="photolist-filter-title">筛选</h2>
                <div class="photolist-filter-grid">
                    <div class="photolist-filter-group" data-field="userid">
                        <label for="filter-author-desktop">作者</label>
                        <input id="filter-author-desktop" type="text" value="<?php echo h($userDisplayName); ?>" autocomplete="off" data-suggest-field="userid" data-target-hidden="filter-userid-desktop">
                        <input id="filter-userid-desktop" type="hidden" name="userid" value="<?php echo $userId; ?>">
                        <div class="photolist-suggestions" data-suggestions-for="userid"></div>
                    </div>
                    <div class="photolist-filter-group" data-field="airline">
                        <label for="filter-airline-desktop">航司</label>
                        <input id="filter-airline-desktop" type="text" name="airline" value="<?php echo h($airline); ?>" autocomplete="off" data-suggest-field="airline">
                        <div class="photolist-suggestions" data-suggestions-for="airline"></div>
                    </div>
                    <div class="photolist-filter-group" data-field="aircraft_model">
                        <label for="filter-model-desktop">机型</label>
                        <input id="filter-model-desktop" type="text" name="aircraft_model" value="<?php echo h($aircraftModel); ?>" autocomplete="off" data-suggest-field="aircraft_model">
                        <div class="photolist-suggestions" data-suggestions-for="aircraft_model"></div>
                    </div>
                    <div class="photolist-filter-group" data-field="cam">
                        <label for="filter-cam-desktop">相机</label>
                        <input id="filter-cam-desktop" type="text" name="cam" value="<?php echo h($cam); ?>" autocomplete="off" data-suggest-field="cam">
                        <div class="photolist-suggestions" data-suggestions-for="cam"></div>
                    </div>
                    <div class="photolist-filter-group" data-field="lens">
                        <label for="filter-lens-desktop">镜头</label>
                        <input id="filter-lens-desktop" type="text" name="lens" value="<?php echo h($lens); ?>" autocomplete="off" data-suggest-field="lens">
                        <div class="photolist-suggestions" data-suggestions-for="lens"></div>
                    </div>
                    <div class="photolist-filter-group" data-field="registration_number">
                        <label for="filter-reg-desktop">序列号</label>
                        <input id="filter-reg-desktop" type="text" name="registration_number" value="<?php echo h($registrationNumber); ?>" autocomplete="off" data-suggest-field="registration_number">
                        <div class="photolist-suggestions" data-suggestions-for="registration_number"></div>
                    </div>
                    <div class="photolist-filter-group" data-field="iatacode">
                        <label for="filter-location-desktop">拍摄地点</label>
                        <input id="filter-location-desktop" type="text" name="iatacode" value="<?php echo h($iataCode); ?>" autocomplete="off" data-suggest-field="iatacode">
                        <div class="photolist-suggestions" data-suggestions-for="iatacode"></div>
                    </div>
                    <div class="photolist-filter-group" data-field="keyword">
                        <label for="filter-keyword-desktop">关键字搜索</label>
                        <input id="filter-keyword-desktop" type="text" name="keyword" value="<?php echo h($keyword); ?>" autocomplete="off" placeholder="标题、作者、机型、序列号等">
                    </div>
                </div>
                <div class="photolist-filter-actions">
                    <button class="photolist-filter-submit" type="submit">应用筛选</button>
                    <a class="photolist-filter-reset" href="photolist.php">清空</a>
                </div>
            </form>
        </section>

        <?php if ($errorMessage !== ''): ?>
            <div class="photolist-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php elseif (empty($photos)): ?>
            <div class="photolist-empty">没有找到符合条件的图片。</div>
        <?php else: ?>
            <section class="photolist-grid" id="photolistGrid"><?php echo photo_feed_render_cards($photos); ?></section>
            <div class="photolist-loading" id="photolistLoading" hidden>正在加载更多图片...</div>
            <button class="photolist-action <?php echo $hasMore ? '' : 'is-end'; ?>" id="photolistAction" type="button"><?php echo $hasMore ? '继续加载' : '已经到底了，点击回到顶部'; ?></button>
            <div class="photolist-sentinel" id="photolistSentinel"></div>
        <?php endif; ?>
    </main>

    <button class="photolist-filter-fab" id="mobileFilterFab" type="button" aria-label="打开筛选">⏷</button>
    <div class="photolist-filter-modal" id="mobileFilterModal" hidden>
        <div class="photolist-filter-sheet">
            <div class="photolist-filter-sheet-header">
                <h2 class="photolist-filter-title">筛选</h2>
                <button class="photolist-filter-close" id="mobileFilterClose" type="button">关闭</button>
            </div>
            <form class="photolist-filter-panel" id="mobileFilterForm" method="get" action="photolist.php">
                <div class="photolist-filter-grid">
                    <div class="photolist-filter-group" data-field="userid">
                        <label for="filter-author-mobile">作者</label>
                        <input id="filter-author-mobile" type="text" value="<?php echo h($userDisplayName); ?>" autocomplete="off" data-suggest-field="userid" data-target-hidden="filter-userid-mobile">
                        <input id="filter-userid-mobile" type="hidden" name="userid" value="<?php echo $userId; ?>">
                        <div class="photolist-suggestions" data-suggestions-for="userid"></div>
                    </div>
                    <div class="photolist-filter-group" data-field="airline">
                        <label for="filter-airline-mobile">航司</label>
                        <input id="filter-airline-mobile" type="text" name="airline" value="<?php echo h($airline); ?>" autocomplete="off" data-suggest-field="airline">
                        <div class="photolist-suggestions" data-suggestions-for="airline"></div>
                    </div>
                    <div class="photolist-filter-group" data-field="aircraft_model">
                        <label for="filter-model-mobile">机型</label>
                        <input id="filter-model-mobile" type="text" name="aircraft_model" value="<?php echo h($aircraftModel); ?>" autocomplete="off" data-suggest-field="aircraft_model">
                        <div class="photolist-suggestions" data-suggestions-for="aircraft_model"></div>
                    </div>
                    <div class="photolist-filter-group" data-field="cam">
                        <label for="filter-cam-mobile">相机</label>
                        <input id="filter-cam-mobile" type="text" name="cam" value="<?php echo h($cam); ?>" autocomplete="off" data-suggest-field="cam">
                        <div class="photolist-suggestions" data-suggestions-for="cam"></div>
                    </div>
                    <div class="photolist-filter-group" data-field="lens">
                        <label for="filter-lens-mobile">镜头</label>
                        <input id="filter-lens-mobile" type="text" name="lens" value="<?php echo h($lens); ?>" autocomplete="off" data-suggest-field="lens">
                        <div class="photolist-suggestions" data-suggestions-for="lens"></div>
                    </div>
                    <div class="photolist-filter-group" data-field="registration_number">
                        <label for="filter-reg-mobile">序列号</label>
                        <input id="filter-reg-mobile" type="text" name="registration_number" value="<?php echo h($registrationNumber); ?>" autocomplete="off" data-suggest-field="registration_number">
                        <div class="photolist-suggestions" data-suggestions-for="registration_number"></div>
                    </div>
                    <div class="photolist-filter-group" data-field="iatacode">
                        <label for="filter-location-mobile">拍摄地点</label>
                        <input id="filter-location-mobile" type="text" name="iatacode" value="<?php echo h($iataCode); ?>" autocomplete="off" data-suggest-field="iatacode">
                        <div class="photolist-suggestions" data-suggestions-for="iatacode"></div>
                    </div>
                    <div class="photolist-filter-group" data-field="keyword">
                        <label for="filter-keyword-mobile">关键字搜索</label>
                        <input id="filter-keyword-mobile" type="text" name="keyword" value="<?php echo h($keyword); ?>" autocomplete="off" placeholder="标题、作者、机型、序列号等">
                    </div>
                </div>
                <div class="photolist-filter-actions">
                    <button class="photolist-filter-submit" type="submit">应用筛选</button>
                    <a class="photolist-filter-reset" href="photolist.php">清空</a>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/src/footer.php'; ?>

    <script>
        (function () {
            const initialSuggestions = <?php echo json_encode($initialSuggestions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const suggestUrl = new URL('api/photo_filter_suggest.php', window.location.href);
            const forms = [document.getElementById('desktopFilterForm'), document.getElementById('mobileFilterForm')].filter(Boolean);
            const fieldLabels = {
                userid: '作者',
                airline: '航司',
                aircraft_model: '机型',
                cam: '相机',
                lens: '镜头',
                registration_number: '序列号',
                iatacode: '拍摄地点'
            };

            function buildFilterParams(form) {
                const params = new URLSearchParams();
                ['userid', 'airline', 'aircraft_model', 'cam', 'lens', 'registration_number', 'iatacode', 'keyword'].forEach((name) => {
                    const element = form.querySelector(`[name="${name}"]`);
                    if (element && element.value.trim() !== '') {
                        params.set(name, element.value.trim());
                    }
                });
                return params;
            }

            function renderSuggestionButtons(container, field, items, input, hiddenInput) {
                container.innerHTML = '';
                items.forEach((item) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'photolist-suggestion';
                    button.textContent = `${item.label} (${item.count})`;
                    button.addEventListener('click', () => {
                        input.value = item.label;
                        if (hiddenInput) {
                            hiddenInput.value = item.value;
                        }
                    });
                    container.appendChild(button);
                });
                if (items.length === 0) {
                    const empty = document.createElement('span');
                    empty.className = 'photolist-suggestion';
                    empty.textContent = '暂无匹配';
                    empty.style.cursor = 'default';
                    empty.style.background = '#f3f6fb';
                    empty.style.color = '#86909c';
                    container.appendChild(empty);
                }
            }

            forms.forEach((form) => {
                form.querySelectorAll('[data-suggest-field]').forEach((input) => {
                    const field = input.dataset.suggestField;
                    const hiddenId = input.dataset.targetHidden || '';
                    const hiddenInput = hiddenId ? document.getElementById(hiddenId) : null;
                    const container = input.parentElement.querySelector(`[data-suggestions-for="${field}"]`);
                    const bootstrapItems = initialSuggestions[field] || [];
                    renderSuggestionButtons(container, field, bootstrapItems, input, hiddenInput);

                    let timer = null;
                    input.addEventListener('input', () => {
                        if (hiddenInput) {
                            hiddenInput.value = '';
                        }
                        clearTimeout(timer);
                        timer = setTimeout(async () => {
                            const params = buildFilterParams(form);
                            params.set('field', field);
                            params.set('q', input.value.trim());
                            const url = new URL(suggestUrl);
                            url.search = params.toString();
                            try {
                                const response = await fetch(url.toString(), {
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                });
                                const data = await response.json();
                                if (!response.ok || !data.success) {
                                    throw new Error(data.error || `${fieldLabels[field]}筛选加载失败`);
                                }
                                renderSuggestionButtons(container, field, data.items || [], input, hiddenInput);
                            } catch (error) {
                                renderSuggestionButtons(container, field, [], input, hiddenInput);
                            }
                        }, 220);
                    });
                });
            });

            const mobileFab = document.getElementById('mobileFilterFab');
            const mobileModal = document.getElementById('mobileFilterModal');
            const mobileClose = document.getElementById('mobileFilterClose');
            if (mobileFab && mobileModal && mobileClose) {
                mobileFab.addEventListener('click', () => {
                    mobileModal.hidden = false;
                });
                mobileClose.addEventListener('click', () => {
                    mobileModal.hidden = true;
                });
                mobileModal.addEventListener('click', (event) => {
                    if (event.target === mobileModal) {
                        mobileModal.hidden = true;
                    }
                });
            }
        })();
    </script>

    <?php if ($errorMessage === '' && !empty($photos)): ?>
        <script>
            (function () {
                const grid = document.getElementById('photolistGrid');
                const sentinel = document.getElementById('photolistSentinel');
                const loading = document.getElementById('photolistLoading');
                const action = document.getElementById('photolistAction');

                if (!grid || !sentinel || !action) {
                    return;
                }

                let currentPage = <?php echo $page; ?>;
                let isLoading = false;
                let hasMore = <?php echo $hasMore ? 'true' : 'false'; ?>;
                let loadFailed = false;
                const apiUrl = new URL('api/photo_feed.php', window.location.href);
                apiUrl.searchParams.set('iatacode', '<?php echo h($iataCode); ?>');
                apiUrl.searchParams.set('userid', '<?php echo $userId; ?>');
                apiUrl.searchParams.set('airline', '<?php echo h($airline); ?>');
                apiUrl.searchParams.set('aircraft_model', '<?php echo h($aircraftModel); ?>');
                apiUrl.searchParams.set('cam', '<?php echo h($cam); ?>');
                apiUrl.searchParams.set('lens', '<?php echo h($lens); ?>');
                apiUrl.searchParams.set('registration_number', '<?php echo h($registrationNumber); ?>');
                apiUrl.searchParams.set('keyword', '<?php echo h($keyword); ?>');
                apiUrl.searchParams.set('per_page', '<?php echo $filters['per_page']; ?>');
                apiUrl.searchParams.set('expires', '<?php echo $apiAccess['expires']; ?>');
                apiUrl.searchParams.set('sig', '<?php echo h($apiAccess['signature']); ?>');

                function setState() {
                    loading.hidden = !isLoading;
                    action.disabled = isLoading;
                    action.textContent = hasMore ? (isLoading ? '正在加载...' : '继续加载') : '已经到底了，点击回到顶部';
                    if (loadFailed && !isLoading && hasMore) {
                        action.textContent = '继续加载';
                    }
                    action.classList.toggle('is-end', !hasMore);
                }

                async function loadMore() {
                    if (isLoading || !hasMore) {
                        return;
                    }

                    isLoading = true;
                    loadFailed = false;
                    setState();

                    const nextUrl = new URL(apiUrl);
                    nextUrl.searchParams.set('page', String(currentPage + 1));

                    try {
                        const response = await fetch(nextUrl.toString(), {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            throw new Error(data.error || '加载失败');
                        }

                        if (data.html) {
                            grid.insertAdjacentHTML('beforeend', data.html);
                            currentPage += 1;
                        }

                        hasMore = Boolean(data.hasMore);
                        loadFailed = false;
                    } catch (error) {
                        loadFailed = true;
                        action.textContent = error.message || '继续加载';
                    } finally {
                        isLoading = false;
                        setState();
                    }
                }

                setState();

                action.addEventListener('click', () => {
                    if (hasMore) {
                        loadMore();
                        return;
                    }

                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });

                if (!hasMore) {
                    return;
                }

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            loadMore();
                        }
                    });
                }, {
                    rootMargin: '600px 0px'
                });

                observer.observe(sentinel);
            })();
        </script>
    <?php endif; ?>
</body>
</html>
