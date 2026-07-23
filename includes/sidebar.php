<?php
/**
 * includes/topbar.php
 * Shared primary navigation. Include from any protected page.
 *
 * Renders THREE things, CSS decides which shows at which width:
 *   .dash-sidebar   - fixed left sidebar, icon + label (>= 880px)
 *   .mobile-topbar  - slim sticky top bar with brand + logout (< 880px)
 *   .bottom-nav     - fixed bottom icon tab bar, Instagram-style (< 880px)
 *
 * Expects two variables set before including:
 *   $basePath  - '' for root-level pages, '../' for pages one folder deep (income/, expense/)
 *   $activeNav - one of: dashboard | income | expense | reports | budget
 */

$basePath  = $basePath ?? '';
$activeNav = $activeNav ?? '';

function nav_active(string $key, string $active): string
{
    return $key === $active ? ' is-active' : '';
}

/**
 * Nav items shared by the sidebar and the bottom tab bar, so both stay
 * in sync automatically — edit this list once to add/remove a section.
 * Each icon is a plain <path>/<circle> fragment, dropped into a shared
 * <svg viewBox="0 0 24 24"> wrapper (see nav_icon() below).
 */
$navItems = [
    'dashboard' => [
        'label' => 'Dashboard',
        'href'  => 'dashboard.php',
        'icon'  => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9.5a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1V10"/><path d="M9.5 20.5V14h5v6.5"/>',
    ],
    'income' => [
        'label' => 'Income',
        'href'  => 'income/view_income.php',
        'icon'  => '<path d="M4 16.5 10 10.5 13.5 14 20 7.5"/><path d="M14.5 7.5H20V13"/>',
    ],
    'expense' => [
        'label' => 'Expense',
        'href'  => 'expense/view_expense.php',
        'icon'  => '<path d="M4 7.5 10 13.5 13.5 10 20 16.5"/><path d="M14.5 16.5H20V11"/>',
    ],
    'reports' => [
        'label' => 'Reports',
        'href'  => 'reports.php',
        'icon'  => '<path d="M4.5 20V11"/><path d="M12 20V4"/><path d="M19.5 20v-7"/>',
    ],
    'budget' => [
        'label' => 'Budget',
        'href'  => 'budget.php',
        'icon'  => '<circle cx="12" cy="12" r="8.5"/><path d="M12 5v7l5 3"/>',
    ],
];

function nav_icon(string $fragment, string $class = 'nav-icon'): string
{
    return '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $fragment . '</svg>';
}

$logoutIcon = '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>';
?>
<!-- ============ Desktop sidebar (>= 880px) ============ -->
<aside class="dash-sidebar" aria-label="Primary navigation">
  <a href="<?= $basePath ?>dashboard.php" class="sidebar-brand">
    <span class="brand-mark__glyph">₹</span>
    <span class="brand-mark__word">Finance Manager</span>
  </a>

  <nav class="sidebar-nav">
    <?php foreach ($navItems as $key => $item): ?>
      <a href="<?= $basePath . $item['href'] ?>" class="sidebar-nav__link<?= nav_active($key, $activeNav) ?>">
        <?= nav_icon($item['icon']) ?>
        <span class="sidebar-nav__label"><?= $item['label'] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <form method="POST" action="<?= $basePath ?>logout.php" class="sidebar-logout">
    <button type="submit" class="sidebar-nav__link sidebar-nav__link--logout">
      <?= nav_icon($logoutIcon) ?>
      <span class="sidebar-nav__label">Log out</span>
    </button>
  </form>
</aside>

<!-- ============ Mobile top bar (< 880px) ============ -->
<header class="mobile-topbar">
  <a href="<?= $basePath ?>dashboard.php" class="brand-mark brand-mark--dark">
    <span class="brand-mark__glyph">₹</span>
    <span class="brand-mark__word">Finance Manager</span>
  </a>

  <form method="POST" action="<?= $basePath ?>logout.php">
    <button type="submit" class="mobile-topbar__logout" aria-label="Log out">
      <?= nav_icon($logoutIcon, 'nav-icon nav-icon--logout') ?>
    </button>
  </form>
</header>

<!-- ============ Mobile bottom tab bar (< 880px) ============ -->
<nav class="bottom-nav" aria-label="Primary navigation">
  <?php foreach ($navItems as $key => $item): ?>
    <a href="<?= $basePath . $item['href'] ?>" class="bottom-nav__link<?= nav_active($key, $activeNav) ?>">
      <?= nav_icon($item['icon'], 'nav-icon nav-icon--bottom') ?>
      <span class="bottom-nav__label"><?= $item['label'] ?></span>
    </a>
  <?php endforeach; ?>
</nav>