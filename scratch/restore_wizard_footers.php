<?php
$file = __DIR__ . '/../includes/employee-form-steps.php';
$content = file_get_contents($file);

// We want to insert the footer before the closing </div> of each step-content
// Steps 1 to 12.
for ($n = 1; $n <= 12; $n++) {
    $prev = $n - 1;
    $next = $n + 1;
    
    $footer = "\n    <div class=\"wizard-footer\">\n";
    if ($n > 1) {
        $footer .= "        <button type=\"button\" class=\"btn btn-secondary\" onclick=\"showStep($prev)\">\n";
        $footer .= "            <i class=\"fas fa-arrow-left me-2\"></i>Back\n";
        $footer .= "        </button>\n";
    } else {
        $footer .= "        <div></div>\n";
    }
    
    $footer .= "        <div class=\"d-flex gap-2\">\n";
    $footer .= "            <?php if (\$_SESSION['role'] === 'Employee'): ?>\n";
    $footer .= "                <button type=\"button\" onclick=\"autoSaveDraft()\" class=\"btn btn-outline-success\">\n";
    $footer .= "                    <i class=\"fas fa-save me-1\"></i> Save Draft\n";
    $footer .= "                </button>\n";
    $footer .= "            <?php elseif (\$isEdit): ?>\n";
    $footer .= "                <button type=\"submit\" name=\"quick_save\" value=\"1\" class=\"btn btn-outline-success\">\n";
    $footer .= "                    <i class=\"fas fa-save me-1\"></i> Quick Save\n";
    $footer .= "                </button>\n";
    $footer .= "            <?php endif; ?>\n";
    
    if ($n < 12) {
        $footer .= "            <button type=\"button\" class=\"btn btn-primary\" onclick=\"showStep($next)\">\n";
        $footer .= "                Next <i class=\"fas fa-arrow-right ms-2\"></i>\n";
        $footer .= "            </button>\n";
    } else {
        // Step 12 footer for Admin Edit mode
        $footer .= "            <button type=\"submit\" class=\"btn btn-success\">\n";
        $footer .= "                <i class=\"fas fa-save me-2\"></i><?php echo \$isEdit ? 'Update Employee' : 'Save Employee'; ?>\n";
        $footer .= "            </button>\n";
    }
    $footer .= "        </div>\n";
    $footer .= "    </div>\n";

    // Regex to find the end of stepN div
    // We look for the <div class="step-content" id="stepN"> ... </div> (closing tag)
    $pattern = '/(<div class="step-content" id="step' . $n . '"[^>]*>.*?)(<\/div>)/s';
    
    // Check if it exists
    if (preg_match($pattern, $content)) {
        $content = preg_replace($pattern, '$1' . addcslashes($footer, '$') . '$2', $content);
    }
}

file_put_contents($file, $content);
echo "Done. Restored wizard footers to all 12 steps.\n";
?>
