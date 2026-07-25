-- Afrotech Academy — seed.sql
-- Populates the six flier tracks, a starter course catalog, and the
-- editable landing copy. Re-runnable (REPLACE / INSERT IGNORE).

-- ------- Tracks (the six "What you'll learn" programs) ---------------
REPLACE INTO `tracks` (slug, name, icon, summary, outcomes, sort, status) VALUES
('cybersecurity', 'Cybersecurity', 'shield',
 'Stay safe online and learn how the people who protect networks actually think.',
 '["Spot phishing, scams, and unsafe links","Strong passwords & 2FA","Digital footprint & privacy basics","Intro to ethical hacking mindset"]', 1, 'active'),
('google-workspace', 'Google Workspace', 'grid',
 'Get fluent in the tools schools and workplaces run on every day.',
 '["Docs, Sheets & Slides","Gmail & Calendar like a pro","Drive organisation & sharing","Collaboration & version history"]', 2, 'active'),
('ai-automations', 'AI & Automations', 'spark',
 'Use AI responsibly and automate the boring stuff.',
 '["Prompting that works","AI image & text tools","No-code automations","Using AI honestly & safely"]', 3, 'active'),
('coding', 'Coding', 'code',
 'Go from first line of code to a project you can show off.',
 '["Web basics: HTML, CSS, JS","Logic & problem solving","Build a mini web project","Version control fundamentals"]', 4, 'active'),
('graphics-design', 'Graphics Design', 'pen',
 'Design posters, logos, and social graphics that pop.',
 '["Design principles & colour","Canva & vector basics","Logo & poster projects","Building a mini portfolio"]', 5, 'active'),
('digital-marketing', 'Digital Marketing', 'megaphone',
 'Understand how brands grow online — and run a real mini campaign.',
 '["Social media strategy","Content that converts","Basic analytics","Plan & run a mini campaign"]', 6, 'active');

-- ------- Starter course catalog (LMS) --------------------------------
REPLACE INTO `courses` (title, slug, track_slug, level, age_range, weeks, instructor, price_naira, summary, body, syllabus_json, status) VALUES
('Cyber Safety Foundations', 'cyber-safety-foundations', 'cybersecurity', 'Beginner', '7+', 4, 'Afrotech Faculty', 40000,
 'A hands-on introduction to staying safe, private, and smart online.',
 'Young learners explore how the internet works, how attackers try to trick people, and the simple habits that keep them and their families safe. Practical, age-appropriate, and confidence-building.',
 '[{"title":"Week 1 — How the internet works","desc":"Devices, networks, and what really happens when you click."},{"title":"Week 2 — Spotting scams","desc":"Phishing, fake links, and social-engineering red flags."},{"title":"Week 3 — Locking things down","desc":"Passwords, passphrases, and two-factor authentication."},{"title":"Week 4 — Your digital footprint","desc":"Privacy, oversharing, and a good online reputation."}]',
 'published'),
('Coding Kickstart: Build Your First Website', 'coding-kickstart', 'coding', 'Beginner', '9+', 4, 'Afrotech Faculty', 40000,
 'From zero to a real, live web page you built yourself.',
 'Learners write their first HTML, style it with CSS, and add a spark of JavaScript — finishing with a personal web page they can share with family and friends.',
 '[{"title":"Week 1 — Structure with HTML","desc":"Headings, text, images, and links."},{"title":"Week 2 — Style with CSS","desc":"Colour, layout, and making it yours."},{"title":"Week 3 — A little JavaScript","desc":"Buttons that do things and simple interactivity."},{"title":"Week 4 — Ship it","desc":"Polish and publish your first project."}]',
 'published'),
('AI Explorers: Create with AI (Safely)', 'ai-explorers', 'ai-automations', 'Beginner', '8+', 4, 'Afrotech Faculty', 40000,
 'Use AI tools to create, learn, and automate — the honest way.',
 'A guided tour of everyday AI tools, how to prompt them well, and where the responsible limits are. Learners finish having built something real with AI as a helper, not a shortcut.',
 '[{"title":"Week 1 — Meet AI","desc":"What it can and cannot do."},{"title":"Week 2 — Prompting","desc":"Asking good questions to get good answers."},{"title":"Week 3 — Create","desc":"Text, images, and a mini project."},{"title":"Week 4 — Use it right","desc":"Honesty, credit, and safety."}]',
 'published'),
('Design Studio: Posters & Logos', 'design-studio', 'graphics-design', 'Beginner', '8+', 4, 'Afrotech Faculty', 40000,
 'Design eye-catching graphics and start a mini portfolio.',
 'Learners pick up core design principles and use friendly tools to produce posters, a logo, and social graphics — ending the program with a small portfolio to be proud of.',
 '[{"title":"Week 1 — Design eyes","desc":"Colour, contrast, and balance."},{"title":"Week 2 — Tools","desc":"Canva and vector basics."},{"title":"Week 3 — Make a logo","desc":"From idea to finished mark."},{"title":"Week 4 — Portfolio","desc":"Pull your best work together."}]',
 'published');

-- ------- Editable landing content blocks -----------------------------
REPLACE INTO `content_blocks` (key_name, value_text) VALUES
('hero_eyebrow',  'Afrostrength Summer School · Age 7+'),
('hero_title',    'Afrotech Academy'),
('hero_subtitle', 'This summer school is your opportunity to build the skills that matter most for the future. Explore leadership, digital and tech skills, content creation, business, and financial literacy through engaging, practical learning designed to inspire confidence, creativity, and success.'),
('summer_intro',  'Six future-ready tracks. One transformative summer. Register your child today for the Afrotech Academy Summer School.');

-- ------- Settings (nothing hard-coded — all editable in admin) -------
REPLACE INTO `settings` (key_name, value_text) VALUES
('program_name',          'Afrotech Academy Summer School'),
('currency_symbol',       '₦'),
('currency_code',         'NGN'),
('summer_fee',            '40000'),
('min_age',               '7'),
('age_label',             'Age 7+'),
('cohort_label',          'Summer 2026'),
('cohort_start',          '2026-08-04'),
('registration_deadline', '2026-07-31 23:59:00'),
('seats_total',           '120'),
('payment_enabled',       '1'),
('payment_provider',      'paystack'),
('bank_transfer_details', 'Afrostrength Limited · GTBank · 0123456789 (quote your reference code)'),
('whatsapp_phone',        '+234 810 019 1456'),
('contact_email',         'reachus@afrostrength.com');

-- ------- Launch promotion (ribbon + countdown) -----------------------
REPLACE INTO `promotions` (id, title, body, badge, cta_label, cta_href, tone, show_countdown, ends_at, sort, status) VALUES
(1, 'Summer School registration is open', 'Save your child''s place before the deadline — limited seats per campus.', 'NEW', 'Register now', '/summer', 'red', 1, '2026-07-31 23:59:00', 1, 'active');

-- ------- Sample discount code ----------------------------------------
REPLACE INTO `discount_codes` (id, code, description, type, value, max_uses, status, ends_at) VALUES
(1, 'EARLYBIRD', 'Early-bird 15% off the summer program', 'percent', 15, 200, 'active', '2026-07-15 23:59:00'),
(2, 'SIBLING', 'Sibling discount — ₦5,000 off', 'fixed', 5000, NULL, 'active', NULL);

-- ------- First operator (super admin) --------------------------------
-- Password below is the bcrypt hash of "ChangeMe!2026". CHANGE IT immediately
-- after first login, or (better) create your own via:
--   php scripts/create-admin.php <username> <email> <password> super_admin
INSERT IGNORE INTO `admin_users` (staff_id, name, username, email, password_hash, role, status)
VALUES ('AFT-STAFF-0001', 'Academy Owner', 'owner', 'reachus@afrostrength.com',
        '$2y$12$cck1Nf5DeQXv24SwEEhK9u7atqo/AltIdgdEb3p/edH1swc9.aNCi', 'super_admin', 'active');
