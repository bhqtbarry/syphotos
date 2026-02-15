<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>syphotos航空 · 定制帽子抽奖</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: linear-gradient(145deg, #0b1a30 0%, #1b3b5c 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            margin: 0;
            color: #e6f0fa;
        }

        .app-container {
            max-width: 1200px;
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            justify-content: center;
        }

        /* 主抽奖卡片 */
        .raffle-card {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 42px;
            padding: 30px 28px;
            box-shadow: 0 30px 50px rgba(0, 0, 0, 0.5), inset 0 1px 2px rgba(255,255,255,0.1);
            width: 520px;
            transition: all 0.3s;
        }

        .admin-card {
            background: rgba(10, 25, 40, 0.85);
            backdrop-filter: blur(8px);
            border: 1px solid #2c5f8a;
            border-radius: 36px;
            padding: 26px 24px;
            width: 380px;
            box-shadow: 0 20px 30px rgba(0,0,0,0.6);
        }

        h2, h3 {
            font-weight: 500;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .title-icon {
            font-size: 2rem;
        }

        .user-badge {
            background: #1e3f5a;
            border-radius: 60px;
            padding: 12px 22px;
            margin: 20px 0 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-left: 5px solid #ffb347;
            box-shadow: inset 0 2px 5px #0c1e2b;
            font-size: 1rem;
        }

        .user-id {
            font-family: monospace;
            background: #0b1f2d;
            padding: 5px 12px;
            border-radius: 30px;
            color: #aad0f5;
            font-size: 0.9rem;
            border: 1px solid #326a8e;
        }

        .raffle-area {
            text-align: center;
            margin: 30px 0 10px;
        }

        .prize-pointer {
            background: #1f4968;
            border-radius: 60px;
            padding: 8px 20px;
            display: inline-block;
            font-size: 1.2rem;
            border: 1px solid #ffb851;
            color: #ffdfaa;
            margin-bottom: 30px;
        }

        .draw-btn {
            background: linear-gradient(145deg, #f5b042, #e07c2c);
            border: none;
            color: white;
            font-size: 2rem;
            padding: 20px 45px;
            border-radius: 120px;
            font-weight: bold;
            letter-spacing: 4px;
            cursor: pointer;
            box-shadow: 0 15px 0 #914d1a, 0 10px 30px rgba(0,0,0,0.5);
            transition: 0.1s linear;
            width: 100%;
            max-width: 300px;
            margin: 0 auto 25px;
            display: block;
        }

        .draw-btn:active {
            transform: translateY(8px);
            box-shadow: 0 7px 0 #914d1a, 0 15px 25px rgba(0,0,0,0.5);
        }

        .draw-btn:disabled {
            opacity: 0.5;
            transform: translateY(5px);
            box-shadow: 0 10px 0 #6f3b14;
            pointer-events: none;
            filter: grayscale(0.6);
        }

        .result-message {
            background: #0e2637;
            border-radius: 50px;
            padding: 15px 25px;
            font-size: 1.4rem;
            margin: 20px 0;
            border: 1px solid #5688b0;
        }

        .contact-form {
            background: #112f42;
            border-radius: 32px;
            padding: 25px;
            margin-top: 20px;
            border: 1px solid #73b1d7;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 400;
            color: #cae2ff;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            background: #1c4059;
            border: 1px solid #2f78a4;
            border-radius: 40px;
            font-size: 1rem;
            color: white;
            outline: none;
            transition: 0.2s;
        }

        .form-control:focus {
            border-color: #f5b042;
            box-shadow: 0 0 0 3px rgba(245,176,66,0.3);
        }

        .form-hint {
            font-size: 0.8rem;
            color: #aac7e0;
            margin-top: 5px;
        }

        .required:after {
            content: " *";
            color: #ff9f4b;
            font-weight: bold;
        }

        .btn-submit {
            background: #1f9eaf;
            border: none;
            color: white;
            padding: 14px 30px;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: 0.2s;
            border-bottom: 4px solid #0e5f6b;
        }

        .btn-submit:hover {
            background: #2bb9cc;
        }

        .small-note {
            font-size: 0.85rem;
            color: #9bbad0;
            margin-top: 15px;
            text-align: center;
        }

        /* 管理员后台 */
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .admin-lock {
            background: #0e2b3b;
            border: 1px solid #3a6f90;
            border-radius: 40px;
            padding: 8px 15px;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .admin-lock input {
            background: #1c3c4f;
            border: 1px solid #1f6182;
            border-radius: 30px;
            padding: 8px 12px;
            width: 110px;
            color: white;
        }

        .admin-lock button {
            background: #2f6c8f;
            border: none;
            color: white;
            border-radius: 30px;
            padding: 8px 18px;
            cursor: pointer;
        }

        .winner-list {
            list-style: none;
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
            padding-right: 8px;
        }

        .winner-item {
            background: #14374e;
            border-radius: 25px;
            padding: 15px 20px;
            margin-bottom: 12px;
            border-left: 6px solid #f5b042;
            word-break: break-all;
        }

        .winner-wechat {
            font-size: 1.2rem;
            font-weight: 600;
            color: #ffe1a3;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .winner-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 8px;
            font-size: 0.85rem;
            color: #b6d3ed;
        }

        .reset-data {
            background: #3c2e46;
            color: #f0c3a3;
            border: 1px solid #a06b52;
            border-radius: 30px;
            padding: 10px 18px;
            margin-top: 20px;
            width: 100%;
            cursor: pointer;
            font-weight: 500;
        }

        .glow-text {
            text-shadow: 0 0 8px #7fc9ff;
        }

        .footer {
            width: 100%;
            text-align: center;
            color: #5d86a3;
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- 抽奖主面板 -->
    <div class="raffle-card">
        <h2>
            <span class="title-icon">✈️</span> 
            syphotos航空 · 云端抽奖
            <span class="title-icon">🎩</span>
        </h2>
        <div class="user-badge">
            <span>🆔 您的识别码</span>
            <span class="user-id" id="userIdDisplay"></span>
        </div>
        <div class="prize-pointer">
            🎁 本期奖品：定制刺绣帽 (概率15%)
        </div>
        <div class="raffle-area">
            <button class="draw-btn" id="drawBtn">抽奖</button>
        </div>
        <!-- 动态结果区域 -->
        <div id="resultPanel" class="result-message">
            ✨ 点击上方按钮试试手气
        </div>
        <!-- 联系方式表单区域 (中奖后显示) -->
        <div id="contactFormContainer" style="display: none;" class="contact-form">
            <h3 style="margin-bottom: 16px;">📋 请提供联系方式 (微信号必填)</h3>
            <div class="form-group">
                <label class="required">微信号 (用于发送礼品)</label>
                <input type="text" id="wechatInput" class="form-control" placeholder="例如: flyer_2025" autocomplete="off">
                <div class="form-hint">我们不会公开您的微信号，仅用于发货</div>
            </div>
            <div class="form-group">
                <label>📧 邮箱 (选填)</label>
                <input type="email" id="emailInput" class="form-control" placeholder="example@sky.com">
            </div>
            <div class="form-group">
                <label>📱 手机号 (选填)</label>
                <input type="tel" id="mobileInput" class="form-control" placeholder="+86 ...">
            </div>
            <button class="btn-submit" id="submitContactBtn">确认提交</button>
            <div class="small-note">提交后不可修改，管理员将通过微信联系您</div>
        </div>
    </div>

    <!-- 管理员后台卡片 -->
    <div class="admin-card">
        <div class="admin-header">
            <h3>🔒 管理员后台</h3>
            <div class="admin-lock">
                <input type="password" id="adminPwd" placeholder="密码" value="">
                <button id="unlockAdminBtn">解锁</button>
            </div>
        </div>
        <div id="adminPanel" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>🎩 中奖者微信号 (帽子礼品)</span>
                <span style="background: #2a5f7a; padding:4px 12px; border-radius:20px;">共 <span id="winnerCount">0</span> 人</span>
            </div>
            <ul class="winner-list" id="winnerList">
                <li style="color: #7f9fb5; text-align: center; padding: 20px;">暂无中奖记录，或密码错误</li>
            </ul>
            <button class="reset-data" id="resetAllBtn">⚠️ 重置所有数据 (测试用)</button>
        </div>
        <div id="adminLockedMessage" style="color: #c7b58b; margin-top: 20px; text-align: center;">
            🔐 请输入密码查看中奖名单
        </div>
    </div>
    <div class="footer">✈️ 每位乘客仅一次抽奖机会 · 中奖后填写微信号领取syphotos定制帽</div>
</div>

<script>
    (function() {
        // ---------- 配置 ----------
        const ADMIN_PASSWORD = 'admin123';   // 管理员密码 (请勿在界面上显示)
        const RAFFLE_PROBABILITY = 0.15;      // 15%中奖率

        // ---------- 全局变量 ----------
        let currentUserId = null;              // 当前用户唯一ID
        let userState = null;                  // 当前用户抽奖状态
        let raffleRecords = [];                 // 所有中奖并提交的记录 [{ userId, wechat, mobile, email, prize, timestamp }]

        // ---------- 初始化存储 ----------
        function initStorage() {
            // 生成/获取用户ID (存储在localStorage)
            let storedId = localStorage.getItem('syphotos_userId');
            if (!storedId) {
                storedId = 'user_' + Math.random().toString(36).substring(2, 12) + Date.now().toString(36);
                localStorage.setItem('syphotos_userId', storedId);
            }
            currentUserId = storedId;

            // 加载当前用户状态
            const userStateJson = localStorage.getItem(`raffle_state_${currentUserId}`);
            if (userStateJson) {
                try {
                    userState = JSON.parse(userStateJson);
                } catch (e) {
                    userState = null;
                }
            }
            if (!userState) {
                // 初始状态：未抽奖
                userState = {
                    hasDrawn: false,
                    isWinner: false,
                    contactSubmitted: false,
                    contact: { wechat: '', mobile: '', email: '' },
                    drawTime: null
                };
            }

            // 加载全局中奖记录
            const recordsJson = localStorage.getItem('syphotos_raffleRecords');
            if (recordsJson) {
                try {
                    raffleRecords = JSON.parse(recordsJson);
                    // 保证数组
                    if (!Array.isArray(raffleRecords)) raffleRecords = [];
                } catch (e) {
                    raffleRecords = [];
                }
            } else {
                raffleRecords = [];
            }
        }

        // 保存当前用户状态
        function saveUserState() {
            localStorage.setItem(`raffle_state_${currentUserId}`, JSON.stringify(userState));
        }

        // 保存全局中奖记录
        function saveRecords() {
            localStorage.setItem('syphotos_raffleRecords', JSON.stringify(raffleRecords));
        }

        // 更新界面显示
        function renderUI() {
            // 显示用户ID
            document.getElementById('userIdDisplay').innerText = currentUserId ? currentUserId.substring(0, 10) + '…' : '—';

            // 抽奖按钮状态
            const drawBtn = document.getElementById('drawBtn');
            if (userState.hasDrawn) {
                drawBtn.disabled = true;
            } else {
                drawBtn.disabled = false;
            }

            // 结果面板及表单显示逻辑
            const resultPanel = document.getElementById('resultPanel');
            const contactContainer = document.getElementById('contactFormContainer');
            const wechatInput = document.getElementById('wechatInput');
            const emailInput = document.getElementById('emailInput');
            const mobileInput = document.getElementById('mobileInput');

            // 预填充微信号 (调取用户数据: 根据ID生成默认微信号)
            const defaultWechat = `wx_${currentUserId ? currentUserId.slice(-8) : 'flyer'}`;

            if (!userState.hasDrawn) {
                // 从未抽奖
                resultPanel.innerText = '✨ 点击上方按钮试试手气';
                contactContainer.style.display = 'none';
            } else {
                // 已抽奖
                if (userState.isWinner) {
                    if (!userState.contactSubmitted) {
                        // 中奖但未提交联系方式 -> 显示表单
                        resultPanel.innerText = '🎉 恭喜你！获得syphotos定制帽！请填写下方微信号领取。';
                        contactContainer.style.display = 'block';
                        // 填充默认微信号（如果之前没填过）
                        if (!wechatInput.value) {
                            wechatInput.value = userState.contact.wechat || defaultWechat;
                            emailInput.value = userState.contact.email || '';
                            mobileInput.value = userState.contact.mobile || '';
                        }
                    } else {
                        // 中奖且已提交
                        resultPanel.innerText = '✅ 已登记领奖信息，感谢参与！我们将通过微信联系您。';
                        contactContainer.style.display = 'none';
                    }
                } else {
                    // 未中奖
                    resultPanel.innerText = '😢 很遗憾，未中奖。感谢参与，欢迎下次活动。';
                    contactContainer.style.display = 'none';
                }
            }

            // 管理员面板已经解锁？单独处理，但列表内容需更新
            // 如果管理员面板可见，刷新中奖列表
            if (document.getElementById('adminPanel').style.display === 'block') {
                renderWinnerList();
            }
        }

        // 渲染中奖列表 (后台)
        function renderWinnerList() {
            const winnerListEl = document.getElementById('winnerList');
            const winnerCountEl = document.getElementById('winnerCount');
            // 只显示已提交联系方式的记录（且是中奖者）
            const validWinners = raffleRecords.filter(r => r && r.wechat && r.wechat.trim() !== '').sort((a,b) => (b.timestamp || 0) - (a.timestamp || 0));

            winnerCountEl.innerText = validWinners.length;

            if (validWinners.length === 0) {
                winnerListEl.innerHTML = '<li style="color: #7f9fb5; text-align: center; padding: 20px;">暂无中奖者微信号</li>';
                return;
            }

            let htmlStr = '';
            validWinners.forEach(w => {
                const date = w.timestamp ? new Date(w.timestamp).toLocaleString() : '未知时间';
                htmlStr += `<li class="winner-item">
                    <div class="winner-wechat">💬 ${w.wechat}</div>
                    <div class="winner-meta">
                        <span>🎁 ${w.prize || '定制帽'}</span>
                        <span>📅 ${date}</span>
                        ${w.mobile ? '<span>📱 ' + w.mobile + '</span>' : ''}
                        ${w.email ? '<span>📧 ' + w.email + '</span>' : ''}
                    </div>
                </li>`;
            });
            winnerListEl.innerHTML = htmlStr;
        }

        // 抽奖逻辑
        function performDraw() {
            if (userState.hasDrawn) {
                alert('您已经抽过奖了，每位乘客仅限一次。');
                renderUI();
                return;
            }

            // 决定是否中奖
            const r = Math.random();
            const winner = r < RAFFLE_PROBABILITY;

            userState.hasDrawn = true;
            userState.isWinner = winner;
            userState.drawTime = Date.now();
            userState.contactSubmitted = false;
            userState.contact = { wechat: '', mobile: '', email: '' }; // 重置联系方式

            saveUserState();
            renderUI();

            // 如果未中奖，无额外动作；中奖等待表单填写
        }

        // 提交联系方式
        function submitContact() {
            if (!userState.hasDrawn || !userState.isWinner || userState.contactSubmitted) {
                alert('当前无法提交联系方式。');
                return;
            }

            const wechat = document.getElementById('wechatInput').value.trim();
            if (!wechat) {
                alert('微信号不能为空，用于发送礼品。');
                return;
            }

            // 简单微信号格式验证 (非空即可)
            const email = document.getElementById('emailInput').value.trim();
            const mobile = document.getElementById('mobileInput').value.trim();

            // 更新用户状态
            userState.contactSubmitted = true;
            userState.contact = {
                wechat: wechat,
                email: email,
                mobile: mobile
            };

            // 添加到全局中奖记录 (去重: 同一个用户只保留最新记录)
            const existingIndex = raffleRecords.findIndex(r => r.userId === currentUserId);
            const newRecord = {
                userId: currentUserId,
                wechat: wechat,
                email: email,
                mobile: mobile,
                prize: 'syphotos定制帽子',
                timestamp: Date.now()
            };
            if (existingIndex !== -1) {
                raffleRecords[existingIndex] = newRecord;
            } else {
                raffleRecords.push(newRecord);
            }

            saveUserState();
            saveRecords();

            // 重新渲染
            renderUI();
            // 如果管理员面板开着，刷新列表
            if (document.getElementById('adminPanel').style.display === 'block') {
                renderWinnerList();
            }
            alert('联系方式已提交！管理员将通过微信联系您寄送帽子。');
        }

        // 重置所有数据 (测试用)
        function resetAllData() {
            if (!confirm('确认重置所有数据？这将清除所有用户和中奖记录。')) return;
            localStorage.clear(); // 简单粗暴，但会清除所有本域数据
            // 重新初始化
            initStorage();
            // 重置管理员面板为锁定
            document.getElementById('adminPanel').style.display = 'none';
            document.getElementById('adminLockedMessage').style.display = 'block';
            document.getElementById('adminPwd').value = '';
            renderUI();
        }

        // 解锁管理员后台
        function unlockAdmin() {
            const pwd = document.getElementById('adminPwd').value;
            if (pwd === ADMIN_PASSWORD) {
                document.getElementById('adminPanel').style.display = 'block';
                document.getElementById('adminLockedMessage').style.display = 'none';
                renderWinnerList();  // 立即刷新列表
            } else {
                alert('密码错误');
            }
        }

        // 页面加载初始化
        window.addEventListener('load', function() {
            initStorage();
            renderUI();

            // 绑定事件
            document.getElementById('drawBtn').addEventListener('click', performDraw);
            document.getElementById('submitContactBtn').addEventListener('click', submitContact);
            document.getElementById('unlockAdminBtn').addEventListener('click', unlockAdmin);
            document.getElementById('resetAllBtn').addEventListener('click', resetAllData);

            // 可选: 监听密码框回车
            document.getElementById('adminPwd').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') unlockAdmin();
            });

            // 如果之前已经中奖未提交，表单会显示，确保预填用户数据（调取用户数据体现）
            // 预填已在renderUI中处理
        });
    })();
</script>
</body>
</html>
