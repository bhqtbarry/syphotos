<?php
// nav.php 不输出 <html> <body>，只管导航
?>
  <style>
        :root {
            --primary: #165DFF;
            --primary-light: #4080FF;
            --primary-dark: #0E42D2;
            --secondary: #69b1ff;
            --accent: #FF7D00;
            --light-bg: #f0f7ff;
            --light-gray: #f5f7fa;
            --medium-gray: #e5e9f2;
            --text-dark: #1d2129;
            --text-medium: #4e5969;
            --text-light: #86909c;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* 滚动行为平滑 */
        html {
            scroll-behavior: smooth;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* 导航栏样式 */
        .nav {
            background-color: var(--primary);
            padding: 15px 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
            transition: var(--transition);
        }

        .nav.scrolled {
            padding: 10px 0;
            background-color: rgba(22, 93, 255, 0.95);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .logo img {
            height: 40px;
            width: auto;
            border-radius: 4px;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: bold;
        }

        /* 导航链接 */
        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav a {
            color: white;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 4px;
            transition: var(--transition);
            font-weight: 500;
            position: relative;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nav a:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: white;
            transition: var(--transition);
        }

        .nav a:hover::after {
            width: 100%;
        }

        /* 移动端菜单 */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 110;
        }

        .mobile-menu-btn span {
            position: absolute;
            width: 30px;
            height: 3px;
            background-color: white;
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        .mobile-menu-btn span:nth-child(1) {
            top: 5px;
        }

        .mobile-menu-btn span:nth-child(2) {
            top: 14px;
        }

        .mobile-menu-btn span:nth-child(3) {
            bottom: 5px;
        }

        .mobile-menu-btn.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
        }

        .mobile-menu-btn.active span:nth-child(2) {
            opacity: 0;
        }

        .mobile-menu-btn.active span:nth-child(3) {
            transform: rotate(-45deg) translate(8px, -8px);
        }

        /* 公告弹窗 */
        .announcement-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            padding: 20px;
        }

        .announcement-modal.active {
            opacity: 1;
            visibility: visible;
        }

        .announcement-content {
            background-color: white;
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            padding: 30px;
            position: relative;
            box-shadow: var(--hover-shadow);
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .announcement-modal.active .announcement-content {
            transform: translateY(0);
        }

        .announcement-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-light);
            transition: var(--transition);
        }

        .announcement-close:hover {
            color: #dc3545;
        }

        .announcement-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--medium-gray);
        }

        .announcement-title {
            font-size: 1.5rem;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .announcement-title i {
            color: var(--accent);
        }

        .announcement-date {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .announcement-text {
            margin-bottom: 20px;
            line-height: 1.8;
        }

        .announcement-slider {
            display: flex;
            overflow: hidden;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }

        .announcement-slide {
            min-width: 100%;
            transition: transform 0.5s ease;
        }

        .announcement-pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }

        .announcement-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--medium-gray);
            cursor: pointer;
            transition: var(--transition);
        }

        .announcement-dot.active {
            background-color: var(--primary);
        }

        .announcement-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--medium-gray);
        }

        .announcement-btn {
            padding: 8px 16px;
            border-radius: var(--border-radius);
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }

        .announcement-btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .announcement-btn-primary:hover {
            background-color: var(--primary-dark);
        }

        /* Hero区域（轮播图） */
        .hero {
            position: relative;
            color: white;
            padding: 0;
            text-align: center;
            margin-bottom: 60px;
            border-radius: 0 0 50% 50% / 20px;
            overflow: hidden;
        }

        /* 轮播图容器 */
        .featured-carousel {
            position: relative;
            z-index: 5;
        }

        .carousel-wrapper {
            display: flex;
            transition: transform 0.5s ease-in-out;
            /* 改为相对高度，适应不同设备 */
            height: 60vh;
            min-height: 400px;
        }

        .carousel-slide {
            min-width: 100%;
            position: relative;
            height: 100%;
        }

        .carousel-slide img {
            width: 100%;
            height: 100%;
            /* 确保图片完整显示且不变形，保持比例 */
            object-fit: contain;
            background-color: #000;
        }

        .carousel-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
            color: white;
            padding: 30px;
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.5s ease;
        }

        .carousel-slide:hover .carousel-caption {
            transform: translateY(0);
            opacity: 1;
        }

        .carousel-title {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .carousel-meta {
            display: flex;
            gap: 20px;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .carousel-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .carousel-control {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .carousel-control.prev {
            left: 20px;
        }

        .carousel-control.next {
            right: 20px;
        }

        .carousel-control:hover {
            background-color: var(--primary);
            transform: translateY(-50%) scale(1.1);
        }

        .carousel-indicators {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 10px;
            z-index: 10;
        }

        .carousel-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .carousel-dot.active {
            background-color: white;
            transform: scale(1.3);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }

        /* 精选标签 */
        .featured-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background-color: var(--accent);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            z-index: 10;
            transition: var(--transition);
        }

        .carousel-slide:hover .featured-badge {
            transform: scale(1.1) rotate(3deg);
        }

        /* 搜索框上方标题 */
        .search-header {
            text-align: center;
            margin-bottom: 15px;
            color: var(--primary-dark);
            font-size: clamp(1.2rem, 3vw, 1.8rem);
            font-weight: bold;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        /* 搜索框样式 */
        .search-container {
            background-color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin: -30px auto 40px;
            max-width: 800px;
            position: relative;
            z-index: 20;
            transition: var(--transition);
        }

        .search-container:hover {
            box-shadow: var(--hover-shadow);
        }

        .search-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-input {
            flex: 1;
            padding: 14px 20px;
            border: 2px solid var(--medium-gray);
            border-radius: 50px;
            font-size: 1rem;
            transition: var(--transition);
            outline: none;
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(22, 93, 255, 0.1);
        }

        .search-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .search-hint {
            color: var(--text-light);
            font-size: 0.85rem;
            margin-top: 10px;
            text-align: center;
        }

        /* 统计信息区域 */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 60px;
        }

        .stat-card {
            background-color: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--hover-shadow);
        }

        .stat-icon {
            font-size: 2.8rem;
            color: var(--primary);
            margin-bottom: 20px;
            transition: var(--transition);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
            color: var(--accent);
        }

        .stat-value {
            font-size: clamp(1.8rem, 4vw, 2.2rem);
            font-weight: bold;
            color: var(--primary-dark);
            margin-bottom: 12px;
            position: relative;
            display: inline-block;
        }

        .stat-label {
            color: var(--text-medium);
            font-size: 1rem;
            font-weight: 500;
        }

        /* 在线状态标识 */
        .online-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            background-color: #00B42A;
            border-radius: 50%;
            margin-right: 5px;
            animation: pulse 2s infinite;
        }

        .admin-names {
            margin-top: 10px;
            color: var(--text-medium);
            font-size: 0.9rem;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: var(--transition);
        }

        .stat-card:hover .admin-names {
            color: var(--primary);
        }

        @keyframes pulse {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(0, 180, 42, 0.7);
            }

            70% {
                transform: scale(1);
                box-shadow: 0 0 0 10px rgba(0, 180, 42, 0);
            }

            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(0, 180, 42, 0);
            }
        }

        /* 图片区域标题 */
        .section-title {
            font-size: clamp(1.5rem, 3vw, 2rem);
            margin-bottom: 35px;
            color: var(--primary-dark);
            position: relative;
            padding-bottom: 12px;
            display: inline-block;
        }

        .section-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 60px;
            height: 4px;
            background-color: var(--primary);
            border-radius: 2px;
        }

        /* 图片网格 */
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }

        .photo-item {
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .photo-item:hover {
            transform: translateY(-10px);
            box-shadow: var(--hover-shadow);
        }

        .photo-category {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: rgba(22, 93, 255, 0.9);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            z-index: 10;
            transition: var(--transition);
        }

        .photo-item:hover .photo-category {
            background-color: var(--accent);
            transform: scale(1.1);
        }

        .photo-img-container {
            height: 220px;
            overflow: hidden;
            position: relative;
        }

        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease, filter 0.5s ease;
        }

        .photo-item:hover img {
            transform: scale(1.1);
            filter: brightness(1.05);
        }

        .photo-img-container::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(transparent 60%, rgba(0, 0, 0, 0.1) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .photo-item:hover .photo-img-container::after {
            opacity: 1;
        }

        .photo-info {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .photo-title {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: var(--primary-dark);
            transition: var(--transition);
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .photo-item:hover .photo-title {
            color: var(--primary);
        }

        .photo-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 0.9rem;
            color: var(--text-medium);
            margin-top: auto;
        }

        .photo-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .photo-meta i {
            color: var(--primary-light);
            width: 16px;
            text-align: center;
        }

        /* 按钮通用样式 */
        .btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 50px;
            transition: var(--transition);
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(22, 93, 255, 0.3);
            font-size: 1rem;
            min-height: 44px;
            min-width: 44px;
            justify-content: center;
        }

        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(22, 93, 255, 0.4);
        }

        .view-more {
            text-align: center;
            margin: 50px 0 80px;
        }

        /* 错误提示 */
        .error-message {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            text-align: center;
            grid-column: 1 / -1;
        }

        .search-empty {
            color: var(--text-medium);
            background-color: var(--light-gray);
            padding: 40px 20px;
            border-radius: var(--border-radius);
            text-align: center;
            grid-column: 1 / -1;
        }

        .search-empty i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--text-light);
        }

        /* 手机端快速上传按钮 */
        .mobile-upload-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(255, 125, 0, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 90;
            text-decoration: none;
            display: none;
            /* 默认隐藏 */
        }

        .mobile-upload-btn:hover {
            transform: scale(1.1) rotate(10deg);
            background-color: #e67200;
            box-shadow: 0 6px 20px rgba(255, 125, 0, 0.5);
        }

        /* 页脚样式 */
        footer {
            background-color: var(--primary-dark);
            color: white;
            padding: 60px 0 30px;
            margin-top: 50px;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-logo {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-logo img {
            height: 40px;
            width: auto;
            border-radius: 4px;
        }

        .footer-desc {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .footer-title {
            font-size: 1.2rem;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 40px;
            height: 3px;
            background-color: var(--accent);
            border-radius: 2px;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            transition: var(--transition);
        }

        .social-links a:hover {
            background-color: var(--accent);
            transform: translateY(-3px) rotate(10deg);
        }

        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        /* 返回顶部按钮 */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            transition: var(--transition);
            opacity: 0;
            visibility: hidden;
            z-index: 99;
            transform: translateY(20px);
        }

        .back-to-top.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        /* 动画效果 */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* 响应式适配 - 重点优化轮播图显示 */
        @media (max-width: 768px) {

            /* 导航 */
            .mobile-menu-btn {
                display: block;
                position: relative;
                width: 30px;
                height: 30px;
            }

            .nav-links {
                position: fixed;
                top: 0;
                right: 0;
                height: 100vh;
                width: 0;
                background-color: var(--primary-dark);
                flex-direction: column;
                justify-content: center;
                align-items: center;
                gap: 30px;
                transition: width 0.3s ease;
                overflow: hidden;
                z-index: 105;
            }

            .nav-links.active {
                width: 80%;
                max-width: 300px;
            }

            .nav a {
                font-size: 1.2rem;
                padding: 10px 20px;
            }

            /* 轮播图 - 手机端优化 */
            .carousel-wrapper {
                height: 50vh;
                min-height: 300px;
            }

            .carousel-slide img {
                /* 确保小屏幕下图片完整显示 */
                object-fit: contain;
                min-height: 100%;
            }

            /* 搜索框 */
            .search-form {
                flex-direction: column;
            }

            .search-input,
            .search-btn {
                width: 100%;
            }

            /* 图片网格 */
            .photo-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: 20px;
            }

            /* 页脚 */
            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .footer-title::after {
                left: 50%;
                transform: translateX(-50%);
            }

            .social-links {
                justify-content: center;
            }

            /* 公告弹窗 */
            .announcement-content {
                padding: 20px;
            }

            /* 显示手机端上传按钮 */
            .mobile-upload-btn {
                display: flex;
            }

            /* 调整返回顶部按钮位置，避免与上传按钮重叠 */
            .back-to-top {
                bottom: 100px;
            }
        }

        @media (max-width: 480px) {

            /* 统计卡片 */
            .stats-container {
                grid-template-columns: 1fr;
            }

            /* 轮播图 - 极小屏幕优化 */
            .carousel-wrapper {
                height: 40vh;
                min-height: 250px;
            }

            .carousel-caption {
                padding: 15px;
            }

            .carousel-title {
                font-size: 1.2rem;
            }

            .carousel-meta {
                flex-wrap: wrap;
                gap: 10px;
                font-size: 0.8rem;
            }

            /* 图片网格 */
            .photo-grid {
                grid-template-columns: 1fr;
            }

            .photo-img-container {
                height: 180px;
            }

            /* 标题 */
            .section-title {
                font-size: 1.6rem;
            }

            /* 查看更多按钮 */
            .view-more {
                margin: 30px 0 50px;
            }

            /* 公告标题 */
            .announcement-title {
                font-size: 1.2rem;
            }
        }
        
    </style>



 <!-- 导航栏 -->
    <div class="nav" id="mainNav">
        <div class="container nav-container">
            <a href="index.php" class="logo">
                <img src="8.jpg" alt="SY Photos">
                <!-- <span class="logo-text">SY Photos</span> -->
            </a>

            <!-- 移动端菜单按钮 -->
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <div class="nav-links" id="navLinks">
                <a href="index.php"><i class="fas fa-home"></i> 首页</a>
                <a href="all_photos.php"><i class="fas fa-images"></i> 全部图片</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="user_center.php"><i class="fas fa-user"></i> 用户中心</a>
                    <a href="upload.php"><i class="fas fa-upload"></i> 上传图片</a>
                    <?php if ($_SESSION['is_admin']): ?>
                        <a href="admin_review.php"><i class="fas fa-tachometer-alt"></i> 管理员后台</a>
                    <?php endif; ?>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> 退出登录</a>
                <?php else: ?>
                    <a href="login.php"><i class="fas fa-sign-in-alt"></i> 登录</a>
                    <a href="register.php"><i class="fas fa-user-plus"></i> 注册</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
   <script>
        // 导航栏滚动效果
        window.addEventListener('scroll', function() {
            const nav = document.getElementById('mainNav');
            const backToTop = document.getElementById('backToTop');

            if (window.scrollY > 50) {
                nav.classList.add('scrolled');
                backToTop.classList.add('show');
            } else {
                nav.classList.remove('scrolled');
                backToTop.classList.remove('show');
            }

            // 滚动触发元素淡入动画
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const elementVisible = 150;
                if (elementTop < window.innerHeight - elementVisible) {
                    element.classList.add('visible');
                }
            });
        });

 

        // 移动端菜单功能
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const navLinks = document.getElementById('navLinks');

        mobileMenuBtn.addEventListener('click', function() {
            this.classList.toggle('active');
            navLinks.classList.toggle('active');
            document.body.classList.toggle('overflow-hidden');
        });

        // 移动端点击导航链接后关闭菜单
        const navLinkItems = document.querySelectorAll('.nav-links a');
        navLinkItems.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    mobileMenuBtn.classList.remove('active');
                    navLinks.classList.remove('active');
                    document.body.classList.remove('overflow-hidden');
                }
            });
        });

        // 数字增长动画（统计卡片）
        function animateValue(id, start, end, duration) {
            const obj = document.getElementById(id);
            if (!obj) return;

            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                obj.innerHTML = Math.floor(progress * (end - start) + start);
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

        // 公告弹窗功能
        function initAnnouncementModal() {
            const modal = document.getElementById('announcementModal');
            if (!modal) return;

            const closeBtn = document.getElementById('announcementClose');
            const confirmBtn = document.getElementById('confirmAnnouncement');
            const slider = document.getElementById('modalAnnouncementSlider');
            const slides = slider ? slider.querySelectorAll('.announcement-slide') : [];
            const prevBtn = document.getElementById('prevAnnouncement');
            const nextBtn = document.getElementById('nextAnnouncement');
            const dots = document.querySelectorAll('.announcement-dot');

            let currentIndex = 0;

            // 1秒后显示弹窗
            setTimeout(() => {
                modal.classList.add('active');
            }, 1000);

            // 关闭弹窗
            const closeModal = () => {
                modal.classList.remove('active');
            };

            closeBtn.addEventListener('click', closeModal);
            confirmBtn.addEventListener('click', closeModal);

            // 点击弹窗外部关闭
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal();
                }
            });

            // 切换公告
            const goToSlide = (index) => {
                if (!slider || slides.length <= 1) return;

                currentIndex = index;
                if (currentIndex < 0) currentIndex = slides.length - 1;
                if (currentIndex >= slides.length) currentIndex = 0;

                slider.style.transform = `translateX(-${currentIndex * 100}%)`;

                // 更新公告日期
                const dateElements = document.querySelectorAll('.announcement-slide .announcement-date');
                const modalDate = document.getElementById('modalAnnouncementDate');
                if (modalDate && dateElements[currentIndex]) {
                    modalDate.textContent = dateElements[currentIndex].textContent;
                }

                // 更新指示器
                dots.forEach((dot, i) => {
                    dot.classList.toggle('active', i === currentIndex);
                });
            };

            // 上一条/下一条按钮
            if (prevBtn) {
                prevBtn.addEventListener('click', () => goToSlide(currentIndex - 1));
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', () => goToSlide(currentIndex + 1));
            }

            // 点击指示器切换公告
            dots.forEach(dot => {
                dot.addEventListener('click', () => {
                    const index = parseInt(dot.getAttribute('data-index'));
                    goToSlide(index);
                });
            });
        }

        // 轮播图功能（使用精选图片）
        function initCarousel() {
            const carouselWrapper = document.getElementById('carouselWrapper');
            if (!carouselWrapper) return;

            const slides = carouselWrapper.querySelectorAll('.carousel-slide');
            const prevBtn = document.getElementById('carouselPrev');
            const nextBtn = document.getElementById('carouselNext');
            const dots = document.querySelectorAll('.carousel-dot');
            const totalSlides = slides.length;
            let currentIndex = 0;
            let slideInterval;

            // 切换轮播图
            function goToSlide(index) {
                if (index < 0) index = totalSlides - 1;
                if (index >= totalSlides) index = 0;

                currentIndex = index;
                const offset = -currentIndex * 100;
                carouselWrapper.style.transform = `translateX(${offset}%)`;

                // 更新指示器
                dots.forEach((dot, i) => {
                    dot.classList.toggle('active', i === currentIndex);
                });
            }

            // 下一张
            function nextSlide() {
                goToSlide(currentIndex + 1);
            }

            // 自动播放（5秒切换一次）
            function startSlideInterval() {
                slideInterval = setInterval(nextSlide, 5000);
            }

            // 停止自动播放
            function stopSlideInterval() {
                clearInterval(slideInterval);
            }

            // 按钮事件
            prevBtn.addEventListener('click', () => {
                stopSlideInterval();
                goToSlide(currentIndex - 1);
                startSlideInterval();
            });

            nextBtn.addEventListener('click', () => {
                stopSlideInterval();
                nextSlide();
                startSlideInterval();
            });

            // 指示器事件
            dots.forEach(dot => {
                dot.addEventListener('click', () => {
                    stopSlideInterval();
                    const index = parseInt(dot.getAttribute('data-index'));
                    goToSlide(index);
                    startSlideInterval();
                });
            });

            // 鼠标悬停停止播放
            carouselWrapper.addEventListener('mouseenter', stopSlideInterval);
            carouselWrapper.addEventListener('mouseleave', startSlideInterval);

            // 启动自动播放
            startSlideInterval();
        }



        // 窗口 resize 时重置导航菜单
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                mobileMenuBtn.classList.remove('active');
                navLinks.classList.remove('active');
                document.body.classList.remove('overflow-hidden');
            }
        });
    </script>