
<?php
/**
 * includes/categories.php
 * Standardized category lists used by the Add/Edit Income and Expense
 * forms, the filter dropdowns on the View pages, and the Budget module.
 */


const INCOME_CATEGORIES = ['Salary', 'Freelance', 'Business', 'Investment', 'Other'];

const EXPENSE_CATEGORIES = ['Food', 'Travel', 'Shopping', 'Bills', 'Entertainment', 'Education', 'Healthcare', 'Other'];

/**
 * Renders <option> tags for a category <select>, marking $selected as chosen.
 * If $selected isn't in the list (e.g. legacy free-text data), it's appended
 * so existing rows don't silently lose their category on the next save.
 */
function category_options(array $categories, string $selected = ''): string
{
    if ($selected !== '' && !in_array($selected, $categories, true)) {
        $categories[] = $selected;
    }

    $html = '';
    foreach ($categories as $cat) {
        $isSelected = $cat === $selected ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') . '"' . $isSelected . '>'
            . htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}