<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>PLSNHS - High School Enrollment System | Placido L. Señor National High School</title>
    <style>
        /* ===== RESET & BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #1e293b;
            background-color: #f8fafc;
            scroll-behavior: smooth;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* ===== TYPOGRAPHY & HIGHLIGHTS ===== */
        .highlight {
            color: #0a4723ff;
        }

        .section-title {
            font-size: 2.5rem;
            text-align: center;
            margin-bottom: 3rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        /* ===== NAVBAR ===== */
        .navbar {
            background: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(2px);
        }

        .nav-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #0a6127ff, #2caa61ff);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-menu a {
            text-decoration: none;
            color: #334155;
            font-weight: 600;
            transition: 0.3s;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            color: #0B4F2E;
        }

        .nav-buttons .btn-login-nav {
            background: #0B4F2E;
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 2px 6px rgba(59,130,246,0.3);
        }

        .nav-buttons .btn-login-nav:hover {
            background: #0B4F2E;
            transform: translateY(-2px);
        }

        /* ===== HERO SECTION ===== */
        .hero {
            padding: 5rem 0;
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
        }

        .hero-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 3rem;
            flex-wrap: wrap;
        }

        .hero-content {
            flex: 1;
            min-width: 280px;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.2rem;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            color: #475569;
            margin-bottom: 2rem;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-enroll-now {
            background: #0B4F2E;
            color: white;
            padding: 0.9rem 2rem;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 700;
            transition: 0.3s;
            box-shadow: 0 6px 14px rgba(59,130,246,0.3);
        }

        .btn-enroll-now:hover {
            background: #0B4F2E;
            transform: translateY(-3px);
        }

        .btn-hero-login {
            background: transparent;
            color: #0B4F2E;
            padding: 0.9rem 2rem;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 700;
            border: 2px solid #0B4F2E;
            transition: 0.3s;
        }

        .btn-hero-login:hover {
            background: #eff6ff;
            transform: translateY(-2px);
        }

        .hero-image {
            flex: 1;
            display: flex;
            justify-content: center;
        }

        .illustration {
            width: 280px;
            height: 280px;
            background: rgba(59,130,246,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .circle {
            width: 80px;
            height: 80px;
            background: #0B4F2E;
            border-radius: 50%;
            margin: 0 8px;
            opacity: 0.7;
            animation: float 3s infinite ease-in-out;
        }

        .circle:nth-child(2) {
            animation-delay: 0.2s;
            background: rgba(21, 151, 65, 1);
        }

        .circle:nth-child(3) {
            animation-delay: 0.4s;
            background: #3abb7bff;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }

        /* ===== FEATURES ===== */
        .features {
            padding: 5rem 0;
            background: white;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: #f8fafc;
            padding: 2rem;
            border-radius: 24px;
            text-align: center;
            transition: 0.3s;
            border: 1px solid #e2e8f0;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 30px -12px rgba(0,0,0,0.1);
            border-color: #b9d0f8;
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 0.8rem;
        }

        /* ===== ABOUT ===== */
        .about {
            padding: 5rem 0;
            background: #f1f5f9;
        }

        .about-content {
            display: flex;
            gap: 3rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .about-text {
            flex: 2;
        }

        .about-text p {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        .about-list {
            list-style: none;
        }

        .about-list li {
            margin-bottom: 0.8rem;
            font-weight: 500;
        }

        .about-stats {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 2rem;
            background: white;
            padding: 2rem;
            border-radius: 28px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #0B4F2E;
        }

        /* ===== CONTACT ===== */
        .contact {
            padding: 5rem 0;
            background: white;
        }

        .contact-content {
            display: flex;
            gap: 3rem;
            flex-wrap: wrap;
        }

        .contact-info {
            flex: 1;
            background: #f8fafc;
            padding: 2rem;
            border-radius: 28px;
        }

        .contact-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            align-items: center;
        }

        .contact-icon {
            font-size: 2rem;
        }

        .btn-submit {
            background: #0B4F2E;
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 40px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-submit:hover {
            background: #0B4F2E;
        }

        /* ===== FOOTER ===== */
        .footer {
            background: #0f172a;
            color: #cbd5e1;
            padding: 3rem 0 1rem;
        }

        .footer-content {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-logo .logo-text {
            font-size: 1.8rem;
            background: linear-gradient(135deg, #94a3b8, #e2e8f0);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }

        .footer-links h4 {
            color: white;
            margin-bottom: 1rem;
        }

        .footer-links ul {
            list-style: none;
        }

        .footer-links a {
            color: #cbd5e1;
            text-decoration: none;
            transition: 0.2s;
        }

        .footer-links a:hover {
            color: #60a5fa;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid #334155;
            font-size: 0.85rem;
        }

        /* responsive */
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }
            .nav-menu {
                gap: 1.5rem;
                flex-wrap: wrap;
                justify-content: center;
            }
            .hero-title {
                font-size: 2.2rem;
            }
            .section-title {
                font-size: 1.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <span class="logo-text">PLSNHS</span>
            </div>
            <ul class="nav-menu">
                <li><a href="#home" class="active">Home</a></li>
                <li><a href="#features">Features</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            <div class="nav-buttons">
                <a href="auth/login.php" class="btn-login-nav">Login</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-container">
            <div class="hero-content">
                <h1 class="hero-title">Welcome to <span class="highlight">PLSNHS</span></h1>
                <p class="hero-subtitle">Your seamless gateway to academic enrollment and management</p>
                <div class="hero-buttons">
                    <a href="auth/register.php" class="btn-enroll-now">Enroll Now</a>
                    <a href="auth/login.php" class="btn-hero-login">Login</a>
                </div>
            </div>
            <div class="hero-image">
                <div class="illustration">
                    <div class="circle"></div>
                    <div class="circle"></div>
                    <div class="circle"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <h2 class="section-title">Why Choose <span class="highlight">Placido L. Señior National Highschool</span></h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">📚</div>
                    <h3>Easy Enrollment</h3>
                    <p>Streamlined online enrollment process for students and parents</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Real-time Tracking</h3>
                    <p>Monitor enrollment status and requirements in real-time</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3>Secure System</h3>
                    <p>Your data is protected with industry-standard security</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3>Mobile Friendly</h3>
                    <p>Access the system anytime, anywhere on any device</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about" id="about">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2 class="section-title">About <span class="highlight">PLSNHS</span></h2>
                    <p>PLSNHS is a modern enrollment management system designed specifically for Placido L. Señor National High School. We streamline the admission process, making it easier for students, parents, and administrators to manage enrollments efficiently.</p>
                    <ul class="about-list">
                        <li>✓ Paperless enrollment process</li>
                        <li>✓ Automated status notifications</li>
                        <li>✓ Integrated document tracking</li>
                        <li>✓ 24/7 accessibility</li>
                    </ul>
                </div>
                <div class="about-stats">
                    <div class="stat-item">
                        <div class="stat-number">500+</div>
                        <div class="stat-label">Students Enrolled</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">50+</div>
                        <div class="stat-label">Staff Members</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">98%</div>
                        <div class="stat-label">Satisfaction Rate</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact" id="contact">
        <div class="container">
            <h2 class="section-title">Get In <span class="highlight">Touch</span></h2>
            <div class="contact-content">
                <div class="contact-info">
                    <div class="contact-item">
                        <div class="contact-icon">📍</div>
                        <div>
                            <h4>Address</h4>
                            <p>Langtad, City of Naga, Cebu</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">📞</div>
                        <div>
                            <h4>Phone</h4>
                            <p>(032) 123-4567</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">✉️</div>
                        <div>
                            <h4>Email</h4>
                            <p>info@PLSNHS.edu.ph</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <span class="logo-text">PLSNHS</span>
                    <p>Your seamless gateway to academic enrollment</p>
                </div>
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 PLSNHS. All rights reserved. | Placido L. Señor National High School</p>
            </div>
        </div>
    </footer>

    <!-- Active nav highlight on scroll -->
    <script>
        (function() {
            const sections = document.querySelectorAll('section');
            const navLinks = document.querySelectorAll('.nav-menu a');
            
            function updateActiveNav() {
                let current = '';
                const scrollPos = window.scrollY + 120;
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.clientHeight;
                    if(scrollPos >= sectionTop && scrollPos < sectionTop + sectionHeight) {
                        current = section.getAttribute('id');
                    }
                });
                navLinks.forEach(link => {
                    link.classList.remove('active');
                    const href = link.getAttribute('href').substring(1);
                    if(href === current) {
                        link.classList.add('active');
                    }
                });
            }
            window.addEventListener('scroll', updateActiveNav);
            window.addEventListener('load', updateActiveNav);
        })();
    </script>
</body>
</html>