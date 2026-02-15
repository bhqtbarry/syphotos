<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Syphotos - 可拖动水印工具</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Microsoft YaHei', sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 25px 30px;
            text-align: center;
        }
        
        h1 {
            font-size: 2.4rem;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }
        
        .subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 300;
        }
        
        .main-content {
            display: flex;
            flex-wrap: wrap;
            padding: 25px;
            gap: 25px;
        }
        
        .image-section {
            flex: 1;
            min-width: 300px;
        }
        
        .controls-section {
            flex: 1;
            min-width: 300px;
            background-color: #f9fafc;
            border-radius: 10px;
            padding: 25px;
            border: 1px solid #eaeef5;
        }
        
        .image-container-wrapper {
            position: relative;
            width: 100%;
            height: 400px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e0e0e0;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .image-container {
            position: relative;
            max-width: 100%;
            max-height: 100%;
            display: inline-block;
        }
        
        #previewImage {
            max-width: 100%;
            max-height: 100%;
            display: block;
            border-radius: 8px;
        }
        
        #watermarkElement {
            position: absolute;
            top: 10px;
            left: 10px;
            pointer-events: all;
            cursor: move;
            user-select: none;
            padding: 0;
            border: 2px dashed transparent;
            transition: border-color 0.3s;
            white-space: nowrap;
            z-index: 10;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: transparent;
        }
        
        #watermarkElement:hover, #watermarkElement.dragging {
            border-color: rgba(106, 17, 203, 0.7);
        }
        
        .watermark-text {
            font-weight: bold;
            letter-spacing: 1px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
            line-height: 1.2;
        }
        
        .watermark-syphotos {
            font-size: 40px;
            margin-bottom: 5px;
        }
        
        .watermark-author {
            font-size: 18px;
            opacity: 0.9;
        }
        
        .upload-area {
            border: 2px dashed #6a11cb;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background-color: #f9f5ff;
            margin-bottom: 20px;
        }
        
        .upload-area:hover {
            background-color: #f0ebff;
            transform: translateY(-3px);
        }
        
        .upload-icon {
            font-size: 48px;
            color: #6a11cb;
            margin-bottom: 15px;
        }
        
        .control-group {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eaeef5;
        }
        
        .control-group:last-child {
            border-bottom: none;
        }
        
        .control-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #2c3e50;
            display: flex;
            align-items: center;
        }
        
        .control-title i {
            margin-right: 10px;
            color: #6a11cb;
        }
        
        .slider-container {
            margin-bottom: 15px;
        }
        
        .slider-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .slider-label span:last-child {
            font-weight: 600;
            color: #6a11cb;
        }
        
        input[type="range"] {
            width: 100%;
            height: 8px;
            -webkit-appearance: none;
            background: #e0e0e0;
            border-radius: 4px;
            outline: none;
        }
        
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #6a11cb;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        input[type="range"]::-webkit-slider-thumb:hover {
            background: #5a0cb9;
            transform: scale(1.1);
        }
        
        .position-controls {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 15px;
        }
        
        .position-btn {
            padding: 12px;
            background-color: white;
            border: 1px solid #d0d7e2;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            color: #4a5568;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .position-btn:hover {
            background-color: #f0f4ff;
            border-color: #6a11cb;
        }
        
        .position-btn.active {
            background-color: #6a11cb;
            color: white;
            border-color: #6a11cb;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        button {
            flex: 1;
            padding: 16px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary {
            background-color: #6a11cb;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #5a0cb9;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }
        
        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background-color: #e0e0e0;
            transform: translateY(-3px);
        }
        
        button i {
            margin-right: 8px;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 0.9rem;
            border-top: 1px solid #eaeef5;
            background-color: #f9fafc;
        }
        
        .drag-hint {
            background-color: rgba(106, 17, 203, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin-top: 15px;
            text-align: center;
            color: #6a11cb;
            font-size: 0.9rem;
            border: 1px dashed rgba(106, 17, 203, 0.3);
        }
        
        .user-info {
            background-color: rgba(106, 17, 203, 0.05);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            border: 1px solid rgba(106, 17, 203, 0.1);
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
            margin-right: 15px;
        }
        
        .user-details h3 {
            margin-bottom: 5px;
            color: #333;
        }
        
        .user-details p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .watermark-style-controls {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .style-btn {
            flex: 1;
            padding: 10px;
            border: 1px solid #d0d7e2;
            border-radius: 6px;
            background-color: white;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .style-btn:hover {
            background-color: #f0f4ff;
            border-color: #6a11cb;
        }
        
        .style-btn.active {
            background-color: #6a11cb;
            color: white;
            border-color: #6a11cb;
        }
        
        @media (max-width: 768px) {
            .main-content {
                flex-direction: column;
            }
            
            .image-container-wrapper {
                height: 300px;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .watermark-syphotos {
                font-size: 30px;
            }
            
            .watermark-author {
                font-size: 14px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Syphotos - 可拖动水印</h1>
            <p class="subtitle">自由拖动水印到图片内任意位置，水印包含作者名称</p>
        </header>
        
        <div class="main-content">
            <div class="image-section">
                <div class="image-container-wrapper">
                    <div class="image-container" id="imageContainer">
                        <img id="previewImage" src="" alt="预览图片">
                        <div id="watermarkElement">
                            <div class="watermark-text watermark-syphotos">syphotos</div>
                            <div class="watermark-text watermark-author" id="authorNameDisplay">@photographer</div>
                        </div>
                    </div>
                </div>
                
                <div class="user-info">
                    <div class="user-avatar" id="userAvatar">P</div>
                    <div class="user-details">
                        <h3 id="userNameDisplay">摄影师</h3>
                        <p>已登录，您的水印将自动包含您的名称</p>
                    </div>
                </div>
                
                <div class="upload-area" id="uploadArea">
                    <div class="upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <h3>点击上传图片</h3>
                    <p>支持 JPG、PNG 格式，最大 5MB</p>
                    <input type="file" id="fileInput" accept="image/*" style="display: none;">
                </div>
                
                <div class="drag-hint">
                    <i class="fas fa-hand-pointer"></i> 提示：直接拖动水印可以调整位置，水印会自动限制在图片范围内
                </div>
            </div>
            
            <div class="controls-section">
                <div class="control-group">
                    <div class="control-title">
                        <i class="fas fa-tint"></i>
                        <span>水印透明度</span>
                    </div>
                    <div class="slider-container">
                        <div class="slider-label">
                            <span>不透明度</span>
                            <span id="opacityValue">70%</span>
                        </div>
                        <input type="range" id="opacitySlider" min="0" max="100" value="70">
                    </div>
                </div>
                
                <div class="control-group">
                    <div class="control-title">
                        <i class="fas fa-expand-alt"></i>
                        <span>水印大小</span>
                    </div>
                    <div class="slider-container">
                        <div class="slider-label">
                            <span>主文字大小</span>
                            <span id="mainSizeValue">40px</span>
                        </div>
                        <input type="range" id="mainSizeSlider" min="20" max="80" value="40">
                    </div>
                    <div class="slider-container">
                        <div class="slider-label">
                            <span>作者名称大小</span>
                            <span id="authorSizeValue">18px</span>
                        </div>
                        <input type="range" id="authorSizeSlider" min="10" max="30" value="18">
                    </div>
                </div>
                
                <div class="control-group">
                    <div class="control-title">
                        <i class="fas fa-palette"></i>
                        <span>水印颜色</span>
                    </div>
                    <div class="color-options">
                        <div class="color-option active" style="background-color: #ffffff;" data-color="#ffffff"></div>
                        <div class="color-option" style="background-color: #000000;" data-color="#000000"></div>
                        <div class="color-option" style="background-color: #6a11cb;" data-color="#6a11cb"></div>
                        <div class="color-option" style="background-color: #2575fc;" data-color="#2575fc"></div>
                        <div class="color-option" style="background-color: #ff3e3e;" data-color="#ff3e3e"></div>
                    </div>
                </div>
                
                <div class="control-group">
                    <div class="control-title">
                        <i class="fas fa-layer-group"></i>
                        <span>水印样式</span>
                    </div>
                    <div class="watermark-style-controls">
                        <button class="style-btn active" data-style="default">默认样式</button>
                        <button class="style-btn" data-style="simple">简洁样式</button>
                        <button class="style-btn" data-style="bold">粗体样式</button>
                    </div>
                </div>
                
                <div class="control-group">
                    <div class="control-title">
                        <i class="fas fa-arrows-alt"></i>
                        <span>快速定位</span>
                    </div>
                    <div class="position-controls">
                        <div class="position-btn" data-position="top-left">左上</div>
                        <div class="position-btn" data-position="top-center">上中</div>
                        <div class="position-btn" data-position="top-right">右上</div>
                        <div class="position-btn" data-position="middle-left">左中</div>
                        <div class="position-btn active" data-position="center">中心</div>
                        <div class="position-btn" data-position="middle-right">右中</div>
                        <div class="position-btn" data-position="bottom-left">左下</div>
                        <div class="position-btn" data-position="bottom-center">下中</div>
                        <div class="position-btn" data-position="bottom-right">右下</div>
                    </div>
                </div>
                
                <div class="actions">
                    <button class="btn-primary" id="applyWatermark">
                        <i class="fas fa-check-circle"></i>
                        应用水印
                    </button>
                    <button class="btn-secondary" id="downloadImage">
                        <i class="fas fa-download"></i>
                        下载图片
                    </button>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>© 2023 Syphotos 水印工具 | 水印包含作者名称，保护您的图片版权</p>
        </div>
    </div>

    <script>
        // 获取DOM元素
        const fileInput = document.getElementById('fileInput');
        const uploadArea = document.getElementById('uploadArea');
        const previewImage = document.getElementById('previewImage');
        const watermarkElement = document.getElementById('watermarkElement');
        const imageContainer = document.getElementById('imageContainer');
        const authorNameDisplay = document.getElementById('authorNameDisplay');
        const userNameDisplay = document.getElementById('userNameDisplay');
        const userAvatar = document.getElementById('userAvatar');
        
        // 控制元素
        const opacitySlider = document.getElementById('opacitySlider');
        const opacityValue = document.getElementById('opacityValue');
        const mainSizeSlider = document.getElementById('mainSizeSlider');
        const mainSizeValue = document.getElementById('mainSizeValue');
        const authorSizeSlider = document.getElementById('authorSizeSlider');
        const authorSizeValue = document.getElementById('authorSizeValue');
        const colorOptions = document.querySelectorAll('.color-option');
        const positionButtons = document.querySelectorAll('.position-btn');
        const styleButtons = document.querySelectorAll('.style-btn');
        const applyWatermarkBtn = document.getElementById('applyWatermark');
        const downloadImageBtn = document.getElementById('downloadImage');
        
        // 模拟用户登录信息（实际应从用户系统获取）
        const userInfo = {
            id: 12345,
            username: 'photographer',
            displayName: '摄影师',
            avatarColor: '#6a11cb'
        };
        
        // 水印参数
        let watermarkParams = {
            syphotosText: 'syphotos',
            authorText: `@${userInfo.username}`,
            opacity: 0.7,
            mainSize: 40,
            authorSize: 18,
            color: '#ffffff',
            position: 'center',
            style: 'default',
            imageLoaded: false
        };
        
        // 拖动相关变量
        let isDragging = false;
        let startX, startY;
        let initialLeft, initialTop;
        
        // 图片边界
        let imageBounds = {
            left: 0,
            top: 0,
            right: 0,
            bottom: 0
        };
        
        // 初始化
        function init() {
            // 显示用户信息
            displayUserInfo();
            
            // 上传区域点击事件
            uploadArea.addEventListener('click', () => fileInput.click());
            
            // 文件选择事件
            fileInput.addEventListener('change', handleImageUpload);
            
            // 透明度滑块事件
            opacitySlider.addEventListener('input', function() {
                const opacity = this.value;
                opacityValue.textContent = `${opacity}%`;
                watermarkParams.opacity = opacity / 100;
                updateWatermarkStyle();
            });
            
            // 主文字大小滑块事件
            mainSizeSlider.addEventListener('input', function() {
                const size = this.value;
                mainSizeValue.textContent = `${size}px`;
                watermarkParams.mainSize = parseInt(size);
                updateWatermarkStyle();
                if (watermarkParams.imageLoaded) {
                    updateImageBounds();
                    constrainWatermarkToImage();
                }
            });
            
            // 作者名称大小滑块事件
            authorSizeSlider.addEventListener('input', function() {
                const size = this.value;
                authorSizeValue.textContent = `${size}px`;
                watermarkParams.authorSize = parseInt(size);
                updateWatermarkStyle();
                if (watermarkParams.imageLoaded) {
                    updateImageBounds();
                    constrainWatermarkToImage();
                }
            });
            
            // 颜色选项事件
            colorOptions.forEach(option => {
                option.addEventListener('click', function() {
                    colorOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    watermarkParams.color = this.getAttribute('data-color');
                    updateWatermarkStyle();
                });
            });
            
            // 样式按钮事件
            styleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    styleButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    watermarkParams.style = this.getAttribute('data-style');
                    updateWatermarkStyle();
                });
            });
            
            // 位置按钮事件
            positionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    positionButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    watermarkParams.position = this.getAttribute('data-position');
                    setWatermarkToPosition();
                });
            });
            
            // 应用水印按钮事件
            applyWatermarkBtn.addEventListener('click', applyWatermarkToImage);
            
            // 下载图片按钮事件
            downloadImageBtn.addEventListener('click', downloadImage);
            
            // 水印拖动事件
            watermarkElement.addEventListener('mousedown', startDrag);
            watermarkElement.addEventListener('touchstart', startDragTouch);
            
            // 加载默认图片
            loadDefaultImage();
            
            // 窗口大小改变时更新边界
            window.addEventListener('resize', function() {
                if (watermarkParams.imageLoaded) {
                    updateImageBounds();
                    constrainWatermarkToImage();
                }
            });
        }
        
        // 显示用户信息
        function displayUserInfo() {
            authorNameDisplay.textContent = watermarkParams.authorText;
            userNameDisplay.textContent = userInfo.displayName;
            userAvatar.style.background = userInfo.avatarColor;
            userAvatar.textContent = userInfo.displayName.charAt(0);
        }
        
        // 处理图片上传
        function handleImageUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // 验证文件类型
            if (!file.type.match('image.*')) {
                alert('请选择图片文件（JPG、PNG等）');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(event) {
                previewImage.src = event.target.result;
                previewImage.onload = function() {
                    watermarkParams.imageLoaded = true;
                    updateImageBounds();
                    setWatermarkToPosition();
                    updateWatermarkStyle();
                };
            };
            reader.readAsDataURL(file);
        }
        
        // 加载默认图片
        function loadDefaultImage() {
            const canvas = document.createElement('canvas');
            canvas.width = 800;
            canvas.height = 600;
            const defaultCtx = canvas.getContext('2d');
            
            // 绘制渐变背景
            const gradient = defaultCtx.createLinearGradient(0, 0, 800, 600);
            gradient.addColorStop(0, '#6a11cb');
            gradient.addColorStop(1, '#2575fc');
            defaultCtx.fillStyle = gradient;
            defaultCtx.fillRect(0, 0, 800, 600);
            
            // 添加文本
            defaultCtx.fillStyle = 'rgba(255, 255, 255, 0.8)';
            defaultCtx.font = 'bold 60px Arial';
            defaultCtx.textAlign = 'center';
            defaultCtx.textBaseline = 'middle';
            defaultCtx.fillText('上传图片预览区域', 400, 250);
            
            defaultCtx.font = 'bold 30px Arial';
            defaultCtx.fillText('点击上方区域上传您的图片', 400, 350);
            
            // 设置预览图片
            previewImage.src = canvas.toDataURL('image/jpeg');
            previewImage.onload = function() {
                watermarkParams.imageLoaded = true;
                updateImageBounds();
                setWatermarkToPosition();
                updateWatermarkStyle();
            };
        }
        
        // 更新图片边界
        function updateImageBounds() {
            const containerRect = imageContainer.getBoundingClientRect();
            const imgRect = previewImage.getBoundingClientRect();
            
            // 计算图片在容器中的实际位置（相对位置）
            const scaleX = containerRect.width / imgRect.width;
            const scaleY = containerRect.height / imgRect.height;
            
            // 计算图片在容器中的偏移（居中时）
            const offsetX = (containerRect.width - imgRect.width * scaleX) / 2;
            const offsetY = (containerRect.height - imgRect.height * scaleY) / 2;
            
            // 图片边界（相对于容器）
            imageBounds.left = offsetX;
            imageBounds.top = offsetY;
            imageBounds.right = offsetX + imgRect.width * scaleX;
            imageBounds.bottom = offsetY + imgRect.height * scaleY;
        }
        
        // 更新水印样式
        function updateWatermarkStyle() {
            // 设置水印容器透明度
            watermarkElement.style.opacity = watermarkParams.opacity;
            
            // 获取主文字和作者名称元素
            const syphotosElement = watermarkElement.querySelector('.watermark-syphotos');
            const authorElement = watermarkElement.querySelector('.watermark-author');
            
            // 设置文字大小
            syphotosElement.style.fontSize = `${watermarkParams.mainSize}px`;
            authorElement.style.fontSize = `${watermarkParams.authorSize}px`;
            
            // 设置文字颜色
            syphotosElement.style.color = watermarkParams.color;
            authorElement.style.color = watermarkParams.color;
            
            // 根据样式调整
            updateWatermarkStyleByType();
            
            // 设置文字阴影
            const shadowOpacity = watermarkParams.opacity * 0.5;
            const textShadow = `1px 1px 3px rgba(0, 0, 0, ${shadowOpacity})`;
            syphotosElement.style.textShadow = textShadow;
            authorElement.style.textShadow = textShadow;
            
            // 更新作者名称文本
            authorElement.textContent = watermarkParams.authorText;
        }
        
        // 根据样式类型更新水印样式
        function updateWatermarkStyleByType() {
            const syphotosElement = watermarkElement.querySelector('.watermark-syphotos');
            const authorElement = watermarkElement.querySelector('.watermark-author');
            
            switch(watermarkParams.style) {
                case 'default':
                    syphotosElement.style.fontWeight = 'bold';
                    authorElement.style.fontWeight = 'normal';
                    syphotosElement.style.letterSpacing = '1px';
                    authorElement.style.letterSpacing = '0.5px';
                    watermarkElement.style.background = 'transparent';
                    watermarkElement.style.padding = '0';
                    break;
                case 'simple':
                    syphotosElement.style.fontWeight = 'normal';
                    authorElement.style.fontWeight = 'normal';
                    syphotosElement.style.letterSpacing = 'normal';
                    authorElement.style.letterSpacing = 'normal';
                    watermarkElement.style.background = 'transparent';
                    watermarkElement.style.padding = '0';
                    break;
                case 'bold':
                    syphotosElement.style.fontWeight = '900';
                    authorElement.style.fontWeight = 'bold';
                    syphotosElement.style.letterSpacing = '2px';
                    authorElement.style.letterSpacing = '1px';
                    watermarkElement.style.background = 'transparent';
                    watermarkElement.style.padding = '0';
                    break;
            }
        }
        
        // 设置水印到指定位置
        function setWatermarkToPosition() {
            if (!watermarkParams.imageLoaded) return;
            
            updateImageBounds();
            
            const watermarkRect = watermarkElement.getBoundingClientRect();
            
            // 计算水印在容器中的尺寸比例
            const watermarkWidth = watermarkRect.width;
            const watermarkHeight = watermarkRect.height;
            
            // 图片实际可用的边界（减去水印尺寸）
            const availableLeft = imageBounds.left;
            const availableTop = imageBounds.top;
            const availableRight = imageBounds.right - watermarkWidth;
            const availableBottom = imageBounds.bottom - watermarkHeight;
            
            let x, y;
            
            // 根据选择的位置计算坐标
            switch(watermarkParams.position) {
                case 'top-left':
                    x = availableLeft;
                    y = availableTop;
                    break;
                case 'top-center':
                    x = availableLeft + (availableRight - availableLeft) / 2;
                    y = availableTop;
                    break;
                case 'top-right':
                    x = availableRight;
                    y = availableTop;
                    break;
                case 'middle-left':
                    x = availableLeft;
                    y = availableTop + (availableBottom - availableTop) / 2;
                    break;
                case 'center':
                    x = availableLeft + (availableRight - availableLeft) / 2;
                    y = availableTop + (availableBottom - availableTop) / 2;
                    break;
                case 'middle-right':
                    x = availableRight;
                    y = availableTop + (availableBottom - availableTop) / 2;
                    break;
                case 'bottom-left':
                    x = availableLeft;
                    y = availableBottom;
                    break;
                case 'bottom-center':
                    x = availableLeft + (availableRight - availableLeft) / 2;
                    y = availableBottom;
                    break;
                case 'bottom-right':
                    x = availableRight;
                    y = availableBottom;
                    break;
                default:
                    x = availableLeft + (availableRight - availableLeft) / 2;
                    y = availableTop + (availableBottom - availableTop) / 2;
            }
            
            // 确保水印在图片范围内
            x = Math.max(availableLeft, Math.min(x, availableRight));
            y = Math.max(availableTop, Math.min(y, availableBottom));
            
            // 设置水印位置
            watermarkElement.style.left = `${x}px`;
            watermarkElement.style.top = `${y}px`;
        }
        
        // 开始拖动（鼠标）
        function startDrag(e) {
            e.preventDefault();
            
            // 获取初始位置
            startX = e.clientX;
            startY = e.clientY;
            
            // 获取水印当前的位置
            const computedStyle = window.getComputedStyle(watermarkElement);
            initialLeft = parseFloat(computedStyle.left) || 0;
            initialTop = parseFloat(computedStyle.top) || 0;
            
            // 添加拖动类
            watermarkElement.classList.add('dragging');
            isDragging = true;
            
            // 添加事件监听器
            document.addEventListener('mousemove', doDrag);
            document.addEventListener('mouseup', stopDrag);
        }
        
        // 开始拖动（触摸）
        function startDragTouch(e) {
            e.preventDefault();
            
            const touch = e.touches[0];
            startX = touch.clientX;
            startY = touch.clientY;
            
            const computedStyle = window.getComputedStyle(watermarkElement);
            initialLeft = parseFloat(computedStyle.left) || 0;
            initialTop = parseFloat(computedStyle.top) || 0;
            
            watermarkElement.classList.add('dragging');
            isDragging = true;
            
            document.addEventListener('touchmove', doDragTouch);
            document.addEventListener('touchend', stopDrag);
        }
        
        // 执行拖动（鼠标）
        function doDrag(e) {
            if (!isDragging) return;
            e.preventDefault();
            
            // 计算移动距离
            const deltaX = e.clientX - startX;
            const deltaY = e.clientY - startY;
            
            // 计算新位置
            let newX = initialLeft + deltaX;
            let newY = initialTop + deltaY;
            
            // 应用新位置
            watermarkElement.style.left = `${newX}px`;
            watermarkElement.style.top = `${newY}px`;
            
            // 更新位置后立即约束水印到图片范围内
            constrainWatermarkToImage();
            
            // 更新位置按钮状态
            updatePositionButtons();
        }
        
        // 执行拖动（触摸）
        function doDragTouch(e) {
            if (!isDragging) return;
            e.preventDefault();
            
            const touch = e.touches[0];
            
            // 计算移动距离
            const deltaX = touch.clientX - startX;
            const deltaY = touch.clientY - startY;
            
            // 计算新位置
            let newX = initialLeft + deltaX;
            let newY = initialTop + deltaY;
            
            // 应用新位置
            watermarkElement.style.left = `${newX}px`;
            watermarkElement.style.top = `${newY}px`;
            
            // 更新位置后立即约束水印到图片范围内
            constrainWatermarkToImage();
            
            // 更新位置按钮状态
            updatePositionButtons();
        }
        
        // 约束水印到图片范围内
        function constrainWatermarkToImage() {
            if (!watermarkParams.imageLoaded) return;
            
            updateImageBounds();
            
            const watermarkRect = watermarkElement.getBoundingClientRect();
            
            // 计算水印在容器中的当前位置
            let currentX = parseFloat(watermarkElement.style.left) || 0;
            let currentY = parseFloat(watermarkElement.style.top) || 0;
            
            // 计算水印尺寸
            const watermarkWidth = watermarkRect.width;
            const watermarkHeight = watermarkRect.height;
            
            // 图片实际可用的边界（减去水印尺寸）
            const availableLeft = imageBounds.left;
            const availableTop = imageBounds.top;
            const availableRight = imageBounds.right - watermarkWidth;
            const availableBottom = imageBounds.bottom - watermarkHeight;
            
            // 确保水印在图片范围内
            currentX = Math.max(availableLeft, Math.min(currentX, availableRight));
            currentY = Math.max(availableTop, Math.min(currentY, availableBottom));
            
            // 应用约束后的位置
            watermarkElement.style.left = `${currentX}px`;
            watermarkElement.style.top = `${currentY}px`;
        }
        
        // 停止拖动
        function stopDrag() {
            isDragging = false;
            watermarkElement.classList.remove('dragging');
            
            // 移除事件监听器
            document.removeEventListener('mousemove', doDrag);
            document.removeEventListener('mouseup', stopDrag);
            document.removeEventListener('touchmove', doDragTouch);
            document.removeEventListener('touchend', stopDrag);
        }
        
        // 更新位置按钮状态
        function updatePositionButtons() {
            positionButtons.forEach(btn => btn.classList.remove('active'));
        }
        
        // 应用水印到图片（预览）
        function applyWatermarkToImage() {
            alert('水印已应用到图片！您可以拖动水印调整位置，然后下载图片。');
        }
        
        // 下载带水印的图片
        function downloadImage() {
            if (!watermarkParams.imageLoaded) {
                alert('请先上传图片');
                return;
            }
            
            // 创建临时canvas
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            // 设置canvas尺寸为图片原始尺寸
            canvas.width = previewImage.naturalWidth || previewImage.width;
            canvas.height = previewImage.naturalHeight || previewImage.height;
            
            // 绘制原始图片
            ctx.drawImage(previewImage, 0, 0, canvas.width, canvas.height);
            
            // 获取水印在图片上的相对位置
            const watermarkRect = watermarkElement.getBoundingClientRect();
            
            // 计算水印相对于容器的位置
            const watermarkX = parseFloat(watermarkElement.style.left) || 0;
            const watermarkY = parseFloat(watermarkElement.style.top) || 0;
            
            // 计算水印相对于图片的位置比例
            const relX = (watermarkX - imageBounds.left) / (imageBounds.right - imageBounds.left);
            const relY = (watermarkY - imageBounds.top) / (imageBounds.bottom - imageBounds.top);
            
            // 计算水印在原始图片上的位置
            const watermarkCanvasX = relX * canvas.width;
            const watermarkCanvasY = relY * canvas.height;
            
            // 计算文字大小比例
            const mainFontSize = (watermarkParams.mainSize / (imageBounds.bottom - imageBounds.top)) * canvas.height;
            const authorFontSize = (watermarkParams.authorSize / (imageBounds.bottom - imageBounds.top)) * canvas.height;
            
            // 设置水印样式
            ctx.fillStyle = watermarkParams.color;
            ctx.globalAlpha = watermarkParams.opacity;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';
            
            // 绘制主文字 (syphotos)
            ctx.font = `bold ${mainFontSize}px Arial`;
            ctx.fillText('syphotos', watermarkCanvasX, watermarkCanvasY);
            
            // 绘制作者名称
            ctx.font = `normal ${authorFontSize}px Arial`;
            ctx.fillText(watermarkParams.authorText, watermarkCanvasX, watermarkCanvasY + mainFontSize * 1.2);
            
            // 创建下载链接
            const link = document.createElement('a');
            link.download = `syphotos_${userInfo.username}_${Date.now()}.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
        }
        
        // 初始化应用
        init();
    </script>
</body>
</html>
