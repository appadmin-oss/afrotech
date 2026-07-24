<?php

class HomeController extends Controller {
    public function index(): void {
        $this->view('pages/home', [
            'title'       => AFT_NAME . ' — Summer School for the next generation of African builders',
            'tracks'      => Track::all(),
            'courses'     => array_slice(Course::published(), 0, 3),
            'content'     => ContentBlock::all(),
            'bodyClass'   => 'page-home',
        ], 'main');
    }

    public function about(): void {
        $this->view('pages/about', [
            'title'  => 'About · ' . AFT_NAME,
            'tracks' => Track::all(),
        ], 'main');
    }

    public function contact(): void {
        $this->view('pages/contact', [
            'title' => 'Contact · ' . AFT_NAME,
            'sent'  => flash_pop('contact_ok'),
        ], 'main');
    }

    public function submitContact(): void {
        Csrf::require();
        $data = [
            'name'    => $this->input('name', ''),
            'email'   => $this->input('email', ''),
            'phone'   => $this->input('phone', ''),
            'message' => $this->input('message', ''),
        ];
        $v = new Validator($data);
        $v->check('name', ['required', 'max:160'])
          ->check('email', ['required', 'email'])
          ->check('message', ['required', 'min:5', 'max:2000']);

        if ($v->fails()) {
            if ($this->wantsJson()) $this->json(['ok' => false, 'errors' => $v->errors()], 422);
            flash_set('contact_ok', '');
            $this->view('pages/contact', ['title' => 'Contact · ' . AFT_NAME, 'errors' => $v->errors(), 'old' => $data], 'main');
            return;
        }

        if (Database::available()) {
            Database::insert(
                "INSERT INTO inquiries (name, email, phone, message) VALUES (?,?,?,?)",
                [$data['name'], strtolower($data['email']), $data['phone'] ?: null, $data['message']]
            );
        }
        Mailer::send('New enquiry · ' . AFT_NAME,
            render_email('inquiry', $data), $data['email']);

        $msg = "Thanks — we've got your message and will reply within one working day.";
        if ($this->wantsJson()) $this->json(['ok' => true, 'message' => $msg, 'title' => 'Message sent ✓']);
        flash_set('contact_ok', $msg);
        $this->redirect('/contact');
    }

    public function privacy(): void {
        $this->view('pages/legal/privacy', ['title' => 'Privacy · ' . AFT_NAME], 'main');
    }
    public function terms(): void {
        $this->view('pages/legal/terms', ['title' => 'Terms · ' . AFT_NAME], 'main');
    }

    public function robots(): void {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /dashboard\n";
        echo 'Sitemap: ' . url('/sitemap.xml') . "\n";
        exit;
    }

    public function sitemap(): void {
        header('Content-Type: application/xml; charset=utf-8');
        $urls = ['/', '/summer', '/academy', '/about', '/contact', '/legal/privacy', '/legal/terms'];
        foreach (Course::published() as $c) $urls[] = '/academy/' . $c['slug'];
        foreach (Track::all() as $t) $urls[] = '/summer/track/' . $t['slug'];
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) echo '  <url><loc>' . e(url($u)) . "</loc></url>\n";
        echo '</urlset>';
        exit;
    }
}
