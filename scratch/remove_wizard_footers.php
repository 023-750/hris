<?php
// Removes all <div class="wizard-footer ...">...</div> blocks from employee-form-steps.php
$file = __DIR__ . '/../includes/employee-form-steps.php';
$content = file_get_contents($file);

// Remove wizard-footer divs (multi-line, nested divs)
// Strategy: line-by-line removal
$lines = explode("\n", $content);
$out = [];
$skip = 0;
$depth = 0;

for ($i = 0; $i < count($lines); $i++) {
    $line = $lines[$i];

    if ($skip === 0 && preg_match('/class="wizard-footer/', $line)) {
        $skip = 1;
        $depth = substr_count($line, '<div') - substr_count($line, '</div>');
        continue;
    }

    if ($skip) {
        $depth += substr_count($line, '<div') - substr_count($line, '</div>');
        if ($depth <= 0) {
            $skip = 0;
        }
        continue;
    }

    $out[] = $line;
}

file_put_contents($file, implode("\n", $out));
echo "Done. Removed wizard-footer blocks.\n";
?>
