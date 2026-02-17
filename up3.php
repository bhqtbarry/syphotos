<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Syphotos 水印工具 · 飞机图标左置版</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 20px;
}
.container {
  width: 1200px;
  max-width: 100%;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.header {
  background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
  color: white;
  padding: 20px 30px;
  display: flex;
  align-items: center;
  gap: 15px;
}
.header h1 { font-size: 24px; font-weight: 600; }
.header i { font-size: 28px; }
.main-content { display: flex; flex: 1; flex-wrap: wrap; }
.preview-section {
  flex: 2;
  min-width: 500px;
  padding: 30px;
  background: #f8f9fa;
  display: flex;
  flex-direction: column;
}
.upload-area {
  border: 2px dashed #ccc;
  border-radius: 10px;
  padding: 40px 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s;
  background: white;
  margin-bottom: 20px;
}
.upload-area:hover {
  border-color: #6a11cb;
  background: #f0f0f0;
}
.upload-area i { font-size: 48px; color: #6a11cb; margin-bottom: 10px; }
.upload-area p { color: #666; font-size: 16px; }
.image-container {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
  background: #e9ecef;
  border-radius: 10px;
  overflow: hidden;
  min-height: 400px;
  flex: 1;
}
#previewImage {
  max-width: 100%;
  max-height: 500px;
  object-fit: contain;
  display: block;
}
#watermarkElement {
  position: absolute;
  cursor: move;
  user-select: none;
  text-align: center;
  line-height: 1.2;
  transition: opacity 0.1s;
  pointer-events: auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: transparent;
}
/* 图标与主文字水平排列 */
.watermark-icon-text {
  display: flex;
  flex-direction: row;        /* 改为水平排列 */
  align-items: center;        /* 垂直居中对齐 */
  justify-content: center;
  gap: 15px;                  /* 图标与文字间距 */
}
.watermark-icon {
  font-size: 120px; /* 初始值，由JS控制 */
  line-height: 1;
}
.watermark-syphotos {
  font-size: 150px; /* 初始值，由JS控制 */
  font-weight: bold;
  line-height: 1.2;
}
.watermark-author {
  display: block;
  font-size: 60px; /* 初始值，由JS控制 */
  margin-top: 15px;
}
.control-panel {
  flex: 1;
  min-width: 300px;
  padding: 30px;
  background: white;
  border-left: 1px solid #dee2e6;
}
.user-info {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 1px solid #eee;
}
#userAvatar {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: #6a11cb;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  font-size: 20px;
}
.user-details { display: flex; flex-direction: column; }
#userNameDisplay { font-weight: 600; font-size: 16px; }
#authorNameDisplay { color: #6a11cb; font-size: 14px; }
.control-group { margin-bottom: 25px; }
.control-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
  color: #495057;
  font-weight: 500;
}
.slider-container { display: flex; flex-direction: column; gap: 5px; }
.slider-label {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  color: #666;
}
input[type=range] {
  width: 100%;
  height: 6px;
  border-radius: 5px;
  background: #ddd;
  outline: none;
}
.color-options {
  display: flex;
  gap: 15px;
  margin-top: 5px;
}
.color-option {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 3px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.color-option.active { border-color: #6a11cb; transform: scale(1.1); }
.watermark-style-controls {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.style-btn {
  padding: 8px 16px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 20px;
  cursor: pointer;
  transition: all 0.2s;
}
.style-btn.active { background: #6a11cb; color: white; border-color: #6a11cb; }
.actions {
  display: flex;
  gap: 15px;
  margin-top: 30px;
}
.btn-primary {
  flex: 1;
  padding: 12px;
  background: linear-gradient(135deg, #6a11cb, #2575fc);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.btn-primary:hover { opacity: 0.9; }
.btn-secondary {
  flex: 1;
  padding: 12px;
  background: #e9ecef;
  color: #495057;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.btn-secondary:hover { background: #dee2e6; }
.footer {
  text-align: center;
  padding: 15px;
  background: #f8f9fa;
  color: #6c757d;
  font-size: 14px;
  border-top: 1px solid #dee2e6;
}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <i class="fas fa-camera-retro"></i>
    <h1>Syphotos · 飞机图标左置</h1>
  </div>
  <div class="main-content">
    <!-- 预览区域 -->
    <div class="preview-section">
      <div class="upload-area" id="uploadArea">
        <i class="fas fa-cloud-upload-alt"></i>
        <p>点击上传图片，或拖拽图片至此</p>
        <input type="file" id="fileInput" accept="image/*" style="display: none;">
      </div>
      <div class="image-container" id="imageContainer">
        <img id="previewImage" src="#" alt="预览图" style="display: block;">
        <div id="watermarkElement">
          <!-- 飞机图标在左，syphotos在右 -->
          <div class="watermark-icon-text">
            <i class="fas fa-plane watermark-icon"></i>
            <span class="watermark-syphotos">syphotos</span>
          </div>
          <span class="watermark-author">@photographer</span>
        </div>
      </div>
    </div>
    <!-- 控制面板 -->
    <div class="control-panel">
      <div class="user-info">
        <div id="userAvatar">摄</div>
        <div class="user-details">
          <span id="userNameDisplay">摄影师</span>
          <span id="authorNameDisplay">@photographer</span>
        </div>
      </div>
      <!-- 透明度 -->
      <div class="control-group">
        <div class="control-title"><i class="fas fa-water"></i> 透明度</div>
        <div class="slider-container">
          <div class="slider-label">
            <span>透明度</span>
            <span id="opacityValue">70%</span>
          </div>
          <input type="range" id="opacitySlider" min="0" max="100" value="70">
        </div>
      </div>
      <!-- 大小控制（主文字大小，图标和作者按比例跟随） -->
      <div class="control-group">
        <div class="control-title"><i class="fas fa-font"></i> 水印大小（同步缩放）</div>
        <div class="slider-container">
          <div class="slider-label">
            <span>主文字大小</span>
            <span id="mainSizeValue">150px</span>
          </div>
          <input type="range" id="mainSizeSlider" min="20" max="400" value="150">
          <div class="slider-label" style="margin-top:5px;">
            <span>图标大小（自动跟随）</span>
            <span id="iconSizeValue">120px</span>
          </div>
          <div class="slider-label" style="margin-top:5px;">
            <span>作者文字大小（自动跟随）</span>
            <span id="authorSizeValue">60px</span>
          </div>
        </div>
        <div style="font-size:12px; color:#999; margin-top:5px;">图标大小 = 主文字 × 0.8，作者文字 = 主文字 × 0.4</div>
      </div>
      <!-- 黑白颜色切换 -->
      <div class="control-group">
        <div class="control-title"><i class="fas fa-palette"></i> 水印颜色</div>
        <div class="color-options">
          <div class="color-option active" style="background-color: #ffffff;" data-color="white" title="白色"></div>
          <div class="color-option" style="background-color: #000000;" data-color="black" title="黑色"></div>
        </div>
      </div>
      <!-- 作者文字样式（仅作用于作者文字） -->
      <div class="control-group">
        <div class="control-title"><i class="fas fa-layer-group"></i> 作者文字样式</div>
        <div class="watermark-style-controls">
          <button class="style-btn active" data-style="default">默认样式</button>
          <button class="style-btn" data-style="simple">简洁样式</button>
          <button class="style-btn" data-style="bold">粗体样式</button>
        </div>
      </div>
      <div class="actions">
        <button class="btn-primary" id="applyWatermark"><i class="fas fa-check-circle"></i> 应用水印</button>
        <button class="btn-secondary" id="downloadImage"><i class="fas fa-download"></i> 下载图片</button>
      </div>
    </div>
  </div>
  <div class="footer">
    <p>© 2023 Syphotos · 飞机图标左置 | 可拖动、调透明度、黑白切换</p>
  </div>
</div>
<script>
// 获取DOM元素
const fileInput = document.getElementById('fileInput');
const uploadArea = document.getElementById('uploadArea');
const previewImage = document.getElementById('previewImage');
const watermarkElement = document.getElementById('watermarkElement');
const iconElement = document.querySelector('.watermark-icon');
const syphotosElement = document.querySelector('.watermark-syphotos');
const authorSpan = document.querySelector('.watermark-author');
const imageContainer = document.getElementById('imageContainer');
const authorNameDisplay = document.getElementById('authorNameDisplay');
const userNameDisplay = document.getElementById('userNameDisplay');
const userAvatar = document.getElementById('userAvatar');

// 控制元素
const opacitySlider = document.getElementById('opacitySlider');
const opacityValue = document.getElementById('opacityValue');
const mainSizeSlider = document.getElementById('mainSizeSlider');
const mainSizeValue = document.getElementById('mainSizeValue');
const iconSizeValue = document.getElementById('iconSizeValue');
const authorSizeValue = document.getElementById('authorSizeValue');
const colorOptions = document.querySelectorAll('.color-option');
const styleButtons = document.querySelectorAll('.style-btn');
const applyWatermarkBtn = document.getElementById('applyWatermark');
const downloadImageBtn = document.getElementById('downloadImage');

// 模拟用户信息
const userInfo = {
  id: 12345,
  username: 'photographer',
  displayName: '摄影师',
  avatarColor: '#6a11cb'
};

// 水印参数
let watermarkParams = {
  authorText: `@${userInfo.username}`,
  opacity: 0.7,
  mainSize: 150,            // syphotos 文字大小（原图像素）
  iconRatio: 0.8,            // 图标相对于主文字的比例
  authorRatio: 0.4,          // 作者文字相对于主文字的比例
  color: 'white',            // 'white' 或 'black'
  position: 'center',
  style: 'default',
  imageLoaded: false,
  originalWidth: 0,
  originalHeight: 0,
  displayScale: 1
};

// 计算图标大小
function getIconSize() {
  return Math.round(watermarkParams.mainSize * watermarkParams.iconRatio);
}
// 计算作者文字大小
function getAuthorSize() {
  return Math.round(watermarkParams.mainSize * watermarkParams.authorRatio);
}

// 拖动相关
let isDragging = false;
let startX, startY;
let initialLeft, initialTop;
let imageBounds = { left: 0, top: 0, right: 0, bottom: 0 };

// ---------- 初始化 ----------
function init() {
  displayUserInfo();

  uploadArea.addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', handleImageUpload);

  opacitySlider.addEventListener('input', function() {
    const opacity = this.value;
    opacityValue.textContent = `${opacity}%`;
    watermarkParams.opacity = opacity / 100;
    updateWatermarkStyle();
  });

  mainSizeSlider.addEventListener('input', function() {
    const size = parseInt(this.value);
    mainSizeValue.textContent = `${size}px`;
    watermarkParams.mainSize = size;
    iconSizeValue.textContent = getIconSize() + 'px';
    authorSizeValue.textContent = getAuthorSize() + 'px';
    updatePreviewWatermarkSize();
    if (watermarkParams.imageLoaded) {
      updateImageBounds();
      constrainWatermarkToImage();
    }
  });

  colorOptions.forEach(option => {
    option.addEventListener('click', function() {
      colorOptions.forEach(opt => opt.classList.remove('active'));
      this.classList.add('active');
      watermarkParams.color = this.getAttribute('data-color');
      updateWatermarkStyle();
    });
  });

  styleButtons.forEach(button => {
    button.addEventListener('click', function() {
      styleButtons.forEach(btn => btn.classList.remove('active'));
      this.classList.add('active');
      watermarkParams.style = this.getAttribute('data-style');
      updateWatermarkStyle();
    });
  });

  applyWatermarkBtn.addEventListener('click', applyWatermarkToImage);
  downloadImageBtn.addEventListener('click', downloadImage);

  watermarkElement.addEventListener('mousedown', startDrag);
  watermarkElement.addEventListener('touchstart', startDragTouch);

  loadDefaultImage();

  window.addEventListener('resize', function() {
    if (watermarkParams.imageLoaded) {
      updateImageBounds();
      updateScaleAndWatermark();
      updatePreviewWatermarkSize();
      constrainWatermarkToImage();
    }
  });
}

function displayUserInfo() {
  authorNameDisplay.textContent = watermarkParams.authorText;
  userNameDisplay.textContent = userInfo.displayName;
  userAvatar.style.background = userInfo.avatarColor;
  userAvatar.textContent = userInfo.displayName.charAt(0);
  authorSpan.textContent = watermarkParams.authorText;
}

function handleImageUpload(e) {
  const file = e.target.files[0];
  if (!file) return;
  if (!file.type.match('image.*')) {
    alert('请选择图片文件（JPG、PNG等）');
    return;
  }
  const reader = new FileReader();
  reader.onload = function(event) {
    previewImage.src = event.target.result;
    previewImage.onload = function() {
      watermarkParams.originalWidth = this.naturalWidth;
      watermarkParams.originalHeight = this.naturalHeight;
      watermarkParams.imageLoaded = true;
      updateImageBounds();
      updateScaleAndWatermark();
      setWatermarkToPosition();
      updateWatermarkStyle();
    };
  };
  reader.readAsDataURL(file);
}

function loadDefaultImage() {
  const canvas = document.createElement('canvas');
  canvas.width = 800;
  canvas.height = 600;
  const ctx = canvas.getContext('2d');
  const gradient = ctx.createLinearGradient(0, 0, 800, 600);
  gradient.addColorStop(0, '#6a11cb');
  gradient.addColorStop(1, '#2575fc');
  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, 800, 600);
  ctx.fillStyle = 'rgba(255, 255, 255, 0.8)';
  ctx.font = 'bold 60px Arial';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText('上传图片预览区域', 400, 250);
  ctx.font = 'bold 30px Arial';
  ctx.fillText('点击上方区域上传您的图片', 400, 350);
  previewImage.src = canvas.toDataURL('image/jpeg');
  previewImage.onload = function() {
    watermarkParams.originalWidth = this.naturalWidth;
    watermarkParams.originalHeight = this.naturalHeight;
    watermarkParams.imageLoaded = true;
    updateImageBounds();
    updateScaleAndWatermark();
    setWatermarkToPosition();
    updateWatermarkStyle();
  };
}

function updateImageBounds() {
  const containerRect = imageContainer.getBoundingClientRect();
  const imgRect = previewImage.getBoundingClientRect();
  const offsetX = (containerRect.width - imgRect.width) / 2;
  const offsetY = (containerRect.height - imgRect.height) / 2;
  imageBounds.left = offsetX;
  imageBounds.top = offsetY;
  imageBounds.right = offsetX + imgRect.width;
  imageBounds.bottom = offsetY + imgRect.height;
}

function updateScaleAndWatermark() {
  if (!watermarkParams.imageLoaded) return;
  const imgRect = previewImage.getBoundingClientRect();
  watermarkParams.displayScale = imgRect.width / watermarkParams.originalWidth;
}

function updatePreviewWatermarkSize() {
  const scale = watermarkParams.displayScale || 1;
  syphotosElement.style.fontSize = (watermarkParams.mainSize * scale) + 'px';
  iconElement.style.fontSize = (getIconSize() * scale) + 'px';
  authorSpan.style.fontSize = (getAuthorSize() * scale) + 'px';
}

function updateWatermarkStyle() {
  watermarkElement.style.opacity = watermarkParams.opacity;

  // 设置颜色
  const textColor = watermarkParams.color === 'white' ? '#ffffff' : '#000000';
  syphotosElement.style.color = textColor;
  iconElement.style.color = textColor;
  authorSpan.style.color = textColor;

  // 作者文字样式
  updateWatermarkStyleByType();

  const shadowOpacity = watermarkParams.opacity * 0.5;
  const textShadow = `1px 1px 3px rgba(0, 0, 0, ${shadowOpacity})`;
  syphotosElement.style.textShadow = textShadow;
  iconElement.style.textShadow = textShadow;
  authorSpan.style.textShadow = textShadow;
}

function updateWatermarkStyleByType() {
  switch(watermarkParams.style) {
    case 'default':
      authorSpan.style.fontWeight = 'normal';
      authorSpan.style.letterSpacing = '0.5px';
      break;
    case 'simple':
      authorSpan.style.fontWeight = 'normal';
      authorSpan.style.letterSpacing = 'normal';
      break;
    case 'bold':
      authorSpan.style.fontWeight = 'bold';
      authorSpan.style.letterSpacing = '1px';
      break;
  }
}

function setWatermarkToPosition() {
  if (!watermarkParams.imageLoaded) return;
  updateImageBounds();
  const watermarkRect = watermarkElement.getBoundingClientRect();
  const watermarkWidth = watermarkRect.width;
  const watermarkHeight = watermarkRect.height;
  const availableLeft = imageBounds.left;
  const availableTop = imageBounds.top;
  const availableRight = imageBounds.right - watermarkWidth;
  const availableBottom = imageBounds.bottom - watermarkHeight;
  let x = availableLeft + (availableRight - availableLeft) / 2;
  let y = availableTop + (availableBottom - availableTop) / 2;
  watermarkElement.style.left = x + 'px';
  watermarkElement.style.top = y + 'px';
}

function constrainWatermarkToImage() {
  const rect = watermarkElement.getBoundingClientRect();
  const left = parseFloat(watermarkElement.style.left) || 0;
  const top = parseFloat(watermarkElement.style.top) || 0;
  let newLeft = left;
  let newTop = top;
  if (left < imageBounds.left) newLeft = imageBounds.left;
  if (top < imageBounds.top) newTop = imageBounds.top;
  if (left + rect.width > imageBounds.right) newLeft = imageBounds.right - rect.width;
  if (top + rect.height > imageBounds.bottom) newTop = imageBounds.bottom - rect.height;
  watermarkElement.style.left = newLeft + 'px';
  watermarkElement.style.top = newTop + 'px';
}

// 拖动事件
function startDrag(e) {
  e.preventDefault();
  isDragging = true;
  startX = e.clientX;
  startY = e.clientY;
  const left = parseFloat(watermarkElement.style.left) || 0;
  const top = parseFloat(watermarkElement.style.top) || 0;
  initialLeft = left;
  initialTop = top;
  document.addEventListener('mousemove', onDrag);
  document.addEventListener('mouseup', stopDrag);
}
function startDragTouch(e) {
  e.preventDefault();
  const touch = e.touches[0];
  isDragging = true;
  startX = touch.clientX;
  startY = touch.clientY;
  const left = parseFloat(watermarkElement.style.left) || 0;
  const top = parseFloat(watermarkElement.style.top) || 0;
  initialLeft = left;
  initialTop = top;
  document.addEventListener('touchmove', onDragTouch);
  document.addEventListener('touchend', stopDrag);
}
function onDrag(e) {
  if (!isDragging) return;
  e.preventDefault();
  const dx = e.clientX - startX;
  const dy = e.clientY - startY;
  let newLeft = initialLeft + dx;
  let newTop = initialTop + dy;
  const rect = watermarkElement.getBoundingClientRect();
  if (newLeft < imageBounds.left) newLeft = imageBounds.left;
  if (newTop < imageBounds.top) newTop = imageBounds.top;
  if (newLeft + rect.width > imageBounds.right) newLeft = imageBounds.right - rect.width;
  if (newTop + rect.height > imageBounds.bottom) newTop = imageBounds.bottom - rect.height;
  watermarkElement.style.left = newLeft + 'px';
  watermarkElement.style.top = newTop + 'px';
}
function onDragTouch(e) {
  if (!isDragging) return;
  e.preventDefault();
  const touch = e.touches[0];
  const dx = touch.clientX - startX;
  const dy = touch.clientY - startY;
  let newLeft = initialLeft + dx;
  let newTop = initialTop + dy;
  const rect = watermarkElement.getBoundingClientRect();
  if (newLeft < imageBounds.left) newLeft = imageBounds.left;
  if (newTop < imageBounds.top) newTop = imageBounds.top;
  if (newLeft + rect.width > imageBounds.right) newLeft = imageBounds.right - rect.width;
  if (newTop + rect.height > imageBounds.bottom) newTop = imageBounds.bottom - rect.height;
  watermarkElement.style.left = newLeft + 'px';
  watermarkElement.style.top = newTop + 'px';
}
function stopDrag() {
  isDragging = false;
  document.removeEventListener('mousemove', onDrag);
  document.removeEventListener('mouseup', stopDrag);
  document.removeEventListener('touchmove', onDragTouch);
  document.removeEventListener('touchend', stopDrag);
}

function applyWatermarkToImage() {
  alert('应用水印功能需要后端实现。当前预览效果即代表最终水印比例。');
}
function downloadImage() {
  alert('下载功能需要后端生成带水印的图片。');
}

init();
</script>
</body>
</html>
