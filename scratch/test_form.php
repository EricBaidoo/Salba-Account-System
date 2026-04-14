<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<pre>POST DATA:\n";
    print_r($_POST);
    echo "</pre>";
}
$stat = 'present';
$id = 1;
?>
<form method="POST">
    <style>
        .radio-btn { display: none; }
        .radio-label {
            cursor: pointer; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; text-align: center; border: 1px solid #e5e7eb; transition: all 0.2s;
        }
        .radio-btn:checked + .radio-label.present { background-color: #ecfdf5; color: #059669; border-color: #34d399; }
        .radio-btn:checked + .radio-label.late { background-color: #fffbeb; color: #d97706; border-color: #fbbf24; }
        .radio-btn:checked + .radio-label.absent { background-color: #fef2f2; color: #dc2626; border-color: #f87171; }
    </style>
    
    <input type="radio" class="radio-btn" name="attendance[<?= $id ?>]" value="present" id="p_<?= $id ?>" <?= $stat==='present'?'checked':'' ?>>
    <label class="radio-label present" for="p_<?= $id ?>">Present</label>
    
    <input type="radio" class="radio-btn" name="attendance[<?= $id ?>]" value="late" id="l_<?= $id ?>" <?= $stat==='late'?'checked':'' ?>>
    <label class="radio-label late" for="l_<?= $id ?>">Late</label>
    
    <input type="radio" class="radio-btn" name="attendance[<?= $id ?>]" value="absent" id="a_<?= $id ?>" <?= $stat==='absent'?'checked':'' ?>>
    <label class="radio-label absent" for="a_<?= $id ?>">Absent</label>

    <br><br><button type="submit">Submit Base HTML</button>
</form>
