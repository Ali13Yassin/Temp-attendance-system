<!DOCTYPE html>
<html>
<head>
    <title>Dell event</title>
</head>
<body>
    <h2>PHP Basics</h2>
<?php
function readAttendees($filename) {
    $attendees = [];
    try {
        $file = new SplFileObject($filename);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        // Skip the header row
        $file->seek(1);
        while (!$file->eof()) {
            $row = $file->current();
            if (is_array($row) && count($row) >= 5) {
                $attendees[] = [
                    'project_name' => $row[0],
                    'name'         => $row[1],
                    'id'           => $row[2],
                    'present'      => strtolower($row[3]) == 'true',
                    'late'         => strtolower($row[4]) == 'true'
                ];
            }
            $file->next();
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
    return $attendees;
}

function writeAttendees($filename, $attendees) {
    try {
        $file = new SplFileObject($filename, 'w');
        $file->fputcsv(['project_name','name','ID','present','late']);
        foreach ($attendees as $a) {
            $file->fputcsv([
                $a['project_name'],
                $a['name'],
                $a['id'],
                $a['present'] ? 'true' : 'false',
                $a['late'] ? 'true' : 'false'
            ]);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}
?>
</body>
</html>
