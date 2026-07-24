UPDATE entries
SET snapshot_program = CASE LOWER(TRIM(snapshot_program))
    WHEN 'bambini' THEN 'pre-competitive'
    WHEN 'adulti' THEN 'competitive'
    ELSE snapshot_program
END
WHERE LOWER(TRIM(snapshot_program)) IN ('bambini', 'adulti');
