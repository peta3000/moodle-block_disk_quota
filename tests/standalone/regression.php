<?php
namespace core\task {
    class scheduled_task {}
}
namespace {
    if (!in_array(PHP_SAPI, ["cli", "wasm"], true)) {
        exit("CLI only");
    }
    if (!class_exists("PDO") || !in_array("sqlite", \PDO::getAvailableDrivers(), true)) {
        fwrite(STDERR, "This standalone suite requires PDO SQLite, not a running Moodle installation.\n");
        exit(2);
    }
    $pluginroot = dirname(__DIR__, 2);
    error_reporting(E_ALL);
    set_error_handler(function ($severity, $message, $file, $line) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    define("MOODLE_INTERNAL", true);
    define("CONTEXT_COURSE", 50);
    define("CONTEXT_USER", 30);
    $config = [
        "block_disk_quota" => ["quota_gb" => 800, "quota_activeusers" => 300, "warn_when_within_gb_of_limit" => 10],
        "backup" => ["backup_auto_destination" => ""],
    ];
    function get_config($plugin, $key = null)
    {
        global $config;
        return $key === null ? (object) ($config[$plugin] ?? []) : $config[$plugin][$key] ?? false;
    }
    function set_config($key, $value, $plugin)
    {
        global $config;
        $config[$plugin][$key] = $value;
    }
    $string = [];
    require $pluginroot . "/lang/en/block_disk_quota.php";
    function get_string($key, $plugin, $a = null)
    {
        global $string;
        $result = $string[$key] ?? $key;
        if (is_object($a)) {
            foreach (get_object_vars($a) as $name => $value) {
                $result = str_replace('{$a->' . $name . "}", (string) $value, $result);
            }
        } elseif (is_scalar($a)) {
            $result = str_replace('{$a}', (string) $a, $result);
        }
        return $result;
    }
    function s($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
    }
    function fullname($user)
    {
        return $user->firstname . " " . $user->lastname;
    }
    function userdate($value)
    {
        return gmdate("Y-m-d H:i:s", $value);
    }
    class moodle_exception extends \Exception
    {
        public function __construct($key, $plugin)
        {
            parent::__construct($key);
        }
    }
    class moodle_url
    {
        public function __construct(public $path, public $params = []) {}
        public function __toString()
        {
            return $this->path . ($this->params ? "?" . http_build_query($this->params) : "");
        }
    }
    class html_table
    {
        public $head = [];
        public $data = [];
    }
    class html_writer
    {
        public static function tag($name, $content)
        {
            return "<" . $name . ">" . $content . "</" . $name . ">";
        }
        public static function link($url, $text)
        {
            return '<a href="' . s($url) . '">' . $text . "</a>";
        }
        public static function table($table)
        {
            $out = "<table><thead><tr>";
            foreach ($table->head as $h) {
                $out .= "<th>" . $h . "</th>";
            }
            $out .= "</tr></thead><tbody>";
            foreach ($table->data as $row) {
                $out .= "<tr>";
                foreach ($row as $cell) {
                    $out .= "<td>" . $cell . "</td>";
                }
                $out .= "</tr>";
            }
            return $out . "</tbody></table>";
        }
    }
    class plugin_renderer_base
    {
        public $lines = [];
        public function render_from_template($template, $vars)
        {
            if (str_ends_with($template, "_line")) {
                $this->lines[] = $vars;
                return "line";
            }
            if (str_ends_with($template, "disk_quota_usage")) {
                return "<p>" . $vars["space_used"] . "</p>";
            }
            return "table";
        }
    }
    class Transaction
    {
        public function allow_commit() {}
    }
    class DB
    {
        public $pdo;
        public $family = "postgres";
        public $dbbytes = 1000000;
        public $failinsert = false;
        public function __construct()
        {
            $this->pdo = new \PDO("sqlite::memory:");
            $this->pdo->exec(
                "CREATE TABLE files(id INTEGER, filename TEXT, filesize INTEGER, timemodified INTEGER, contextid INTEGER, component TEXT, filearea TEXT, mimetype TEXT, userid INTEGER, contenthash TEXT)",
            );
            $this->pdo->exec("CREATE TABLE context(id INTEGER, contextlevel INTEGER, instanceid INTEGER)");
            $this->pdo->exec("CREATE TABLE course(id INTEGER, fullname TEXT)");
            $this->pdo->exec(
                "CREATE TABLE user(id INTEGER, firstname TEXT, lastname TEXT, firstnamephonetic TEXT, lastnamephonetic TEXT, middlename TEXT, alternatename TEXT, lastaccess INTEGER)",
            );
            $this->pdo->exec(
                "CREATE TABLE block_disk_quota_measurement(id INTEGER PRIMARY KEY, timemeasured INTEGER, usedgb REAL, quotagb REAL)",
            );
            $this->pdo->exec("INSERT INTO course VALUES(2,'Course two')");
            $this->pdo->exec("INSERT INTO context VALUES(20,50,2),(30,30,2)");
            $this->pdo->exec("INSERT INTO user VALUES(2,'Ada','Example','','','','',0)");
            $statement = $this->pdo->prepare("INSERT INTO files VALUES(?,?,?,?,?,?,?,?,?,?)");
            foreach (
                [
                    [1, "course.mbz", 100, 100, 20, "backup", "course"],
                    [2, "user.mbz", 80, 100, 30, "user", "backup"],
                    [3, "zero.mbz", 0, 100, 20, "backup", "course"],
                    [4, "unknown.mbz", 60, 100, 999, "backup", "other"],
                    [5, "automated.mbz", 90, 100, 20, "backup", "automated"],
                ]
                as $f
            ) {
                $statement->execute(array_merge($f, ["application/vnd.moodle.backup", 2, "hash" . $f[0]]));
            }
        }
        private function sql($sql)
        {
            return str_replace(
                ["{files}", "{context}", "{course}", "{user}"],
                ["files", "context", "course", "user"],
                $sql,
            );
        }
        public function get_records_sql($sql, $params = [])
        {
            $stmt = $this->pdo->prepare($this->sql($sql));
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        }
        public function get_dbfamily()
        {
            return $this->family;
        }
        public function get_field_sql($sql, $params = [])
        {
            if (str_contains($sql, "pg_database_size")) {
                if ($this->family !== "postgres") {
                    throw new \Exception("Wrong postgres query");
                }
                return $this->dbbytes;
            }
            if (str_contains($sql, "information_schema.tables")) {
                if ($this->family !== "mysql" || !str_contains($sql, "DATABASE()")) {
                    throw new \Exception("Wrong mysql query");
                }
                return $this->dbbytes;
            }
            $stmt = $this->pdo->prepare($this->sql($sql));
            $stmt->execute($params);
            return $stmt->fetchColumn();
        }
        public function start_delegated_transaction()
        {
            return new Transaction();
        }
        public function insert_record($table, $record)
        {
            if ($this->failinsert) {
                throw new \RuntimeException("Insert failed");
            }
            $vars = (array) $record;
            $stmt = $this->pdo->prepare(
                "INSERT INTO " .
                    $table .
                    "(" .
                    implode(",", array_keys($vars)) .
                    ") VALUES(" .
                    implode(",", array_fill(0, count($vars), "?")) .
                    ")",
            );
            $stmt->execute(array_values($vars));
        }
        public function get_records_select(...$args)
        {
            return [];
        }
    }
    $DB = new DB();
    spl_autoload_register(function ($name) use ($pluginroot) {
        $prefix = "block_disk_quota\\";
        if (str_starts_with($name, $prefix)) {
            require $pluginroot . "/classes/" . str_replace("\\", "/", substr($name, strlen($prefix))) . ".php";
        }
    });
    require $pluginroot . "/renderer.php";
    use block_disk_quota\usage\filesystem_usage;
    use block_disk_quota\usage\storage_usage;
    use block_disk_quota\usage\quota_manager;
    use block_disk_quota\usage\internal_backup_usage;
    function check($condition, $message)
    {
        if (!$condition) {
            throw new \Exception($message);
        }
        echo "PASS $message\n";
    }
    $root = sys_get_temp_dir() . "/disk-quota-regression-" . bin2hex(random_bytes(6));
    mkdir($root);
    function remove_fixture($path)
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $entry) {
                remove_fixture($entry->getPathname());
            }
            rmdir($path);
        } else {
            unlink($path);
        }
    }
    register_shutdown_function(function () use ($root) {
        remove_fixture($root);
    });
    foreach (["data", "code", "data/filedir", "data/kurssicherung", "code/public"] as $d) {
        if (!is_dir($root . "/" . $d)) {
            mkdir($root . "/" . $d);
        }
    }
    file_put_contents($root . "/data/filedir/blob", str_repeat("a", 10000));
    file_put_contents($root . "/code/public/index.php", "<?php");
    file_put_contents($root . "/data/kurssicherung/external.mbz", str_repeat("x", 200));
    file_put_contents($root . "/data/kurssicherung/empty.mbz", "");
    file_put_contents($root . "/data/kurssicherung/unrelated.zip", "ignore");
    $CFG = (object) ["dataroot" => $root . "/data", "root" => $root . "/code", "dirroot" => $root . "/code/public"];
    check(storage_usage::saved() === null, "old measurements cannot masquerade as physical snapshots");
    $render = new block_disk_quota_renderer();
    check(
        str_contains($render->usage_detail(), "No physical storage measurement"),
        "unmeasured UI asks for scheduled task",
    );
    $task = new \block_disk_quota\task\get_disk_usage();
    $task->execute();
    $snapshot = storage_usage::saved();
    check(
        $snapshot["total_bytes"] ===
            $snapshot["database"]["bytes"] + $snapshot["moodledata"]["bytes"] + $snapshot["code"]["bytes"],
        "snapshot total is database plus moodledata plus code",
    );
    check(
        $snapshot["moodledata"]["bytes"] === array_sum($snapshot["moodledata"]["breakdown"]),
        "moodledata child rows reconcile to its total",
    );
    check($snapshot["moodledata"]["breakdown"]["filedir"] > 0, "filedir is physically measured");
    check(
        $snapshot["code"]["path"] === $root . "/code",
        "Moodle public layout measures installation root, not only public",
    );
    check($snapshot["database"]["method"] === "postgres_database_size", "PostgreSQL measurement adapter selected");
    $overview = $render->usage_detail();
    check(
        str_contains($overview, "moodledata.php") &&
            str_contains($overview, "content_types.php") &&
            !str_contains($overview, "<td>video</td>"),
        "overview links to separate hierarchy and content pages",
    );
    check(str_contains($render->moodledata_detail(), "filedir"), "moodledata report contains physical filedir row");
    $content = $render->usage_detail_table();
    check(
        !str_contains($content, "kurssicherung") && str_contains($content, "backups.php"),
        "logical MIME report excludes directories and links backups",
    );
    $DB->family = "mysql";
    check(
        (new \block_disk_quota\usage\database_usage())->measure()["method"] === "mysql_table_estimate",
        "MariaDB/MySQL selects table-and-index estimate",
    );
    $DB->family = "sqlite";
    try {
        (new storage_usage())->measure();
        throw new \Exception("Unexpected support");
    } catch (\RuntimeException $e) {
        check(str_contains($e->getMessage(), "supports PostgreSQL"), "unsupported DB aborts instead of reporting zero");
    }
    $DB->family = "postgres";
    $before = get_config("block_disk_quota", storage_usage::CONFIG_KEY);
    $DB->failinsert = true;
    try {
        $m = new quota_manager();
        $m->record_space_used($m->get_total_disk_space_used(), 800);
    } catch (\RuntimeException $e) {
    }
    $DB->failinsert = false;
    check(
        get_config("block_disk_quota", storage_usage::CONFIG_KEY) === $before,
        "failed measurement write preserves last saved snapshot",
    );
    $olddata = $CFG->dataroot;
    $CFG->dataroot = $root . "/missing";
    try {
        $task->execute();
        throw new \Exception("Missing root accepted");
    } catch (\RuntimeException $e) {
    }
    $CFG->dataroot = $olddata;
    check(
        get_config("block_disk_quota", storage_usage::CONFIG_KEY) === $before,
        "inaccessible root preserves last saved snapshot",
    );
    // Nested root ownership is tested against the same whole tree and inode accounting.
    $CFG->root = $root;
    $nested = (new storage_usage())->measure();
    $whole = (new filesystem_usage())->measure($root);
    check(
        $nested["code"]["bytes"] + $nested["moodledata"]["bytes"] === $whole["bytes"],
        "nested moodledata root is excluded from code total",
    );
    $CFG->root = $root . "/code";
    mkdir($CFG->dataroot . "/inside-code");
    file_put_contents($CFG->dataroot . "/inside-code/index.php", "code");
    $CFG->root = $CFG->dataroot . "/inside-code";
    $reverse = (new storage_usage())->measure();
    $wholedata = (new filesystem_usage())->measure($CFG->dataroot);
    check(
        $reverse["code"]["bytes"] + $reverse["moodledata"]["bytes"] === $wholedata["bytes"],
        "code nested inside moodledata is charged once",
    );
    $CFG->root = $root . "/code";
    $DB->dbbytes = false;
    try {
        (new \block_disk_quota\usage\database_usage())->measure();
        throw new \Exception("Invalid DB size accepted");
    } catch (\RuntimeException $e) {
        check(true, "invalid database size aborts measurement");
    }
    $DB->dbbytes = 1000000;
    if (PHP_SAPI !== "wasm") {
        mkdir($root . "/links");
        file_put_contents($root . "/links/original", str_repeat("x", 8192));
        link($root . "/links/original", $root . "/links/hardlink");
        symlink($root . "/links", $root . "/links/loop");
        $expected = 0;
        foreach ([$root . "/links", $root . "/links/original", $root . "/links/loop"] as $path) {
            $expected += lstat($path)["blocks"] * 512;
        }
        check(
            (new filesystem_usage())->measure($root . "/links")["bytes"] === $expected,
            "native allocated blocks count hard links once and never traverse symlink loops",
        );
    } else {
        echo "SKIP native hard-link inode check: WebAssembly filesystem remaps host inode IDs.\n";
    }
    $config["backup"]["backup_auto_destination"] = $root . "/data/kurssicherung";
    foreach (["user" => 1, "course" => 1, "automated" => 1, "directory" => 1, "other" => 1] as $cat => $expected) {
        check(
            (new internal_backup_usage($cat))->count() === $expected,
            "category " . $cat . " lists only its own backups",
        );
    }
    $user = (new internal_backup_usage("user"))->get_backup_details()[0];
    check(
        $user->user_name === "Ada Example" && $user->course_id === null,
        "user backups show owner without false course-ID collision",
    );
    $auto = (new internal_backup_usage("automated"))->get_backup_details()[0];
    check($auto->course_id == 2, "automated course-area backups retain course association");
    $directory = (new internal_backup_usage("directory"))->get_backup_details()[0];
    check($directory->course_id === null, "directory backups do not invent course associations");
    $all = new internal_backup_usage();
    check($all->count() === 5, "all categories account for all nonempty backup entries");
    check(
        count($all->get_backup_paginated(0, 3)->data) === 3 && count($all->get_backup_paginated(3, 3)->data) === 2,
        "pagination matches category inventory",
    );
    $DB->pdo->exec(
        "INSERT INTO files VALUES(77,'second.mbz',75,100,30,'user','backup','application/vnd.moodle.backup',2,'hash77')",
    );
    $DB->pdo->exec(
        "INSERT INTO files VALUES(78,'third.mbz',70,100,30,'user','backup','application/vnd.moodle.backup',2,'hash78')",
    );
    $r = new block_disk_quota_renderer();
    $rendered = $r->disk_backup_usage(90, 1, "user");
    check(str_contains($rendered, "category=user"), "pagination links retain selected category");
    check(
        count($r->lines) === 1 && isset($r->lines[0]["user_url"]) && !isset($r->lines[0]["course_url"]),
        "renderer clamps stale category page and links user profile",
    );
    $menu = $r->backup_categories();
    check(
        str_contains($menu, "category=automated") && str_contains($menu, "category=directory"),
        "menu has distinct automated backup sources",
    );
    $config["backup"]["backup_auto_destination"] = $root . "/missing";
    check(
        (new internal_backup_usage("course"))->count() === 1,
        "missing directory does not prevent course backup listing",
    );
    try {
        (new internal_backup_usage("directory"))->count();
        throw new \Exception("Missing directory accepted");
    } catch (moodle_exception $e) {
        check($e->getMessage() === "backup_directory_unreadable", "directory view reports inaccessible destination");
    }
    try {
        new internal_backup_usage("invalid");
        throw new \Exception("Category accepted");
    } catch (\InvalidArgumentException $e) {
        check(true, "unknown categories rejected");
    }

    echo "All focused integration checks passed on PHP " . PHP_VERSION . ".\n";
}
