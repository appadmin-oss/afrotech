<footer class="footer">
  <div class="wrap">
    <div class="footer__grid">
      <div>
        <div class="footer__brand"><span class="brand__mark">A</span> Afrotech Academy</div>
        <p style="margin-top:12px;max-width:34ch">The Afrostrength youth technology school — <em>building brands, strengthening legacies</em>. Cybersecurity, AI, coding, design & more for ages 7+.</p>
      </div>
      <div>
        <h5>Academy</h5>
        <ul>
          <li><a href="<?= e(url('/summer')) ?>">Summer School</a></li>
          <li><a href="<?= e(url('/academy')) ?>">Courses</a></li>
          <li><a href="<?= e(url('/about')) ?>">About</a></li>
          <li><a href="<?= e(url('/login')) ?>">Student sign-in</a></li>
        </ul>
      </div>
      <div>
        <h5>Visit</h5>
        <ul>
          <li>CACENTRE, 2 Oremeji Street, Off Bakery Bus Stop, Egbeda, Alimosho, Lagos</li>
          <li>CACENTRE, 18 Camp Davis Road, Orisunbare Phase 2, Ishefun, Alimosho, Lagos</li>
        </ul>
      </div>
      <div>
        <h5>Reach us</h5>
        <ul>
          <li><a href="tel:+2348100191456"><?= e(AFT_PHONE) ?></a></li>
          <li><a href="mailto:<?= e(AFT_EMAIL) ?>"><?= e(AFT_EMAIL) ?></a></li>
          <li>@<?= e(AFT_SOCIAL) ?></li>
        </ul>
      </div>
    </div>
    <div class="footer__bottom">
      <span>© <?= date('Y') ?> <?= e(AFT_PARENT) ?>. All rights reserved.</span>
      <span class="flex" style="gap:16px">
        <a href="<?= e(url('/legal/privacy')) ?>">Privacy</a>
        <a href="<?= e(url('/legal/terms')) ?>">Terms</a>
        <a href="<?= e(url('/admin')) ?>">Operator</a>
      </span>
    </div>
  </div>
</footer>
