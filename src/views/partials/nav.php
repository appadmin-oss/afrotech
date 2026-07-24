<nav class="nav">
  <div class="wrap nav__inner">
    <a class="brand" href="<?= e(url('/')) ?>">
      <span class="brand__mark">A</span>
      Afrotech <span style="color:var(--red)">Academy</span>
    </a>
    <button class="nav__burger" aria-label="Menu" aria-expanded="false">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="nav__links">
      <a href="<?= e(url('/')) ?>" class="<?= current_path()==='/'?'is-active':'' ?>">Home</a>
      <a href="<?= e(url('/summer')) ?>" class="<?= is_active('/summer')?'is-active':'' ?>">Summer School</a>
      <a href="<?= e(url('/academy')) ?>" class="<?= is_active('/academy')?'is-active':'' ?>">Courses</a>
      <a href="<?= e(url('/about')) ?>" class="<?= is_active('/about')?'is-active':'' ?>">About</a>
      <a href="<?= e(url('/contact')) ?>" class="<?= is_active('/contact')?'is-active':'' ?>">Contact</a>
      <?php if (StudentAuth::check()): ?>
        <a href="<?= e(url('/dashboard')) ?>">My Learning</a>
      <?php else: ?>
        <a href="<?= e(url('/login')) ?>">Sign in</a>
      <?php endif; ?>
      <a class="btn btn--primary nav__cta" href="<?= e(url('/summer')) ?>">Register Now</a>
    </div>
  </div>
</nav>
