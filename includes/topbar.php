<?php
/**
 * includes/topbar.php
 * Shared top navigation bar. Include from any protected page.
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
?>
<header class="dash-topbar">
  <a href="<?= $basePath ?>dashboard.php" class="brand-mark brand-mark--dark">
    <span class="brand-mark__glyph">₹</span>
    <span class="brand-mark__word">Finance Manager</span>
  </a>

  <nav class="dash-nav">
    <a href="<?= $basePath ?>dashboard.php" class="dash-nav__link<?= nav_active('dashboard', $activeNav) ?>">Dashboard</a>
    <a href="<?= $basePath ?>income/view_income.php" class="dash-nav__link<?= nav_active('income', $activeNav) ?>">Income</a>
    <a href="<?= $basePath ?>expense/view_expense.php" class="dash-nav__link<?= nav_active('expense', $activeNav) ?>">Expense</a>
    <a href="<?= $basePath ?>reports.php" class="dash-nav__link<?= nav_active('reports', $activeNav) ?>">Reports</a>
    <a href="<?= $basePath ?>budget.php" class="dash-nav__link<?= nav_active('budget', $activeNav) ?>">Budget</a>
  </nav>

  <form method="POST" action="<?= $basePath ?>logout.php" class="dash-topbar__logout">
    <button type="submit" class="btn btn--ghost">
      <svg viewBox="0 0 24 24" class="icon-inline"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
      Log out
    </button>
  </form>
</header>