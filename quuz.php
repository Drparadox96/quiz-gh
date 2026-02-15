
<?php
session_start();

/**
 * AnnkoDeals Quiz with optional referral unlock or pay option.
 * - Visit with ?rc=REFID to register a referral click (one-per-IP).
 * - User can create a referral link (POST create_ref); it is stored in referrals.json.
 * - If referrals for your ref_id >= REF_THRESHOLD, you are auto-unlocked.
 */

// ---------- CONFIG ----------
$REF_FILE = __DIR__ . '/referrals.json';
$REF_THRESHOLD = 10; // number of successful referred unique visitors needed to unlock
$BASE_URL = 'https://annkodeals.com';
// ----------------------------

// Ensure referrals file exists
if (!file_exists($REF_FILE)) {
    file_put_contents($REF_FILE, json_encode(new stdClass(), JSON_PRETTY_PRINT));
}

function load_refs($file) {
    $json = @file_get_contents($file);
    $data = json_decode($json, true);
    if (!is_array($data)) return [];
    return $data;
}

function save_refs($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

function gen_refid($len = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $s = '';
    for ($i=0;$i<$len;$i++) $s .= $chars[random_int(0, strlen($chars)-1)];
    return $s;
}

// Process incoming referral click
if (isset($_GET['rc'])) {
    $refid = preg_replace('/[^a-z0-9]/','', strtolower($_GET['rc']));
    if ($refid) {
        $refs = load_refs($REF_FILE);
        if (isset($refs[$refid])) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $already = in_array($ip, $refs[$refid]['ips'] ?? []);
            if (!$already) {
                $refs[$refid]['count']++;
                $refs[$refid]['ips'][] = $ip;
                $refs[$refid]['last_hit'] = date('c');
                save_refs($REF_FILE, $refs);
            }
        }
    }
    header("Location: index.php");
    exit();
}

// Handle POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Create referral link
    if (isset($_POST['create_ref'])) {
        $refs = load_refs($REF_FILE);
        $new = gen_refid(8);
        while (isset($refs[$new])) $new = gen_refid(8);

        $refs[$new] = [
            'count' => 0,
            'ips' => [],
            'created' => date('c')
        ];
        save_refs($REF_FILE, $refs);

        $_SESSION['ref_id'] = $new;

        header('Content-Type: application/json');
        echo json_encode([
            'ok'=>1,
            'refid'=>$new,
            'link'=>$BASE_URL . '/?rc=' . $new
        ]);
        exit();
    }

    // Check referral status
    if (isset($_POST['check_ref'])) {
        $refid = $_SESSION['ref_id'] ?? null;
        $refs = load_refs($REF_FILE);
        $count = $refid && isset($refs[$refid]) ? $refs[$refid]['count'] : 0;

        if ($count >= $REF_THRESHOLD) {
            $_SESSION['paid'] = true;
            if (!isset($_SESSION['start_time'])) $_SESSION['start_time'] = time();
        }

        header('Content-Type: application/json');
        echo json_encode([
            'ok'=>1,
            'refid'=>$refid,
            'count'=>$count,
            'threshold'=>$REF_THRESHOLD,
            'unlocked'=>!empty($_SESSION['paid'])
        ]);
        exit();
    }

    // Save submission
    if (isset($_POST['save_details'])) {
        $name = trim($_POST['name'] ?? '');
        $momo = trim($_POST['momo'] ?? '');
        $shared = $_POST['shared'] ?? 0;

        if ($name && $momo && $shared == 1) {
            $entry = [
                "name" => $name,
                "momo" => $momo,
                "score" => $_SESSION['score'] ?? 0,
                "time_taken" => $_SESSION['time_taken'] ?? 9999,
                "time" => date("Y-m-d H:i:s")
            ];
            $file = "winners.json";

            $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
            $data[] = $entry;

            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
            session_destroy();

            header("Location: index.php?thanks");
            exit();
        } else {
            header("Location: index.php?result&error=fill");
            exit();
        }
    }
}

// QUESTIONS
$questions = [
    ["q"=>"Capital of Ghana?", "a"=>["Accra","Kumasi","Ho"], "correct"=>0],
    ["q"=>"Currency of Ghana?", "a"=>["Dollar","Cedi","Naira"], "correct"=>1],
    ["q"=>"Official language of Ghana?", "a"=>["English","French","Swahili"], "correct"=>0],
    ["q"=>"Independence year of Ghana?", "a"=>["1957","1966","1948"], "correct"=>0],
    ["q"=>"Ghana is in which continent?", "a"=>["Africa","Asia","Europe"], "correct"=>0],
    ["q"=>"Ghana gained independence from?", "a"=>["USA","UK","France"], "correct"=>1],
    ["q"=>"First President of Ghana?", "a"=>["Kwame Nkrumah","Jerry Rawlings","John Mahama"], "correct"=>0],
    ["q"=>"Major river in Ghana?", "a"=>["Volta","Niger","Congo"], "correct"=>0],
    ["q"=>"Largest city in Ghana?", "a"=>["Accra","Kumasi","Tamale"], "correct"=>0],
    ["q"=>"Famous stadium in Ghana?", "a"=>["Baba Yara","Accra Sports Stadium","Cape Coast Stadium"], "correct"=>0],
    ["q"=>"Which color is NOT in Ghana flag?", "a"=>["Red","Blue","Green"], "correct"=>1],
    ["q"=>"Ghana's national football team nickname?", "a"=>["Black Stars","Super Eagles","Indomitable Lions"], "correct"=>0],
    ["q"=>"Ghana's famous waterfall?", "a"=>["Wli Falls","Victoria Falls","Kaieteur Falls"], "correct"=>0],
    ["q"=>"Major export of Ghana?", "a"=>["Cocoa","Gold","Oil"], "correct"=>0],
    ["q"=>"Largest lake in Ghana?", "a"=>["Lake Volta","Lake Bosumtwi","Lake Chad"], "correct"=>0],
    ["q"=>"Traditional Ghanaian cloth?", "a"=>["Kente","Dashiki","Ankara"], "correct"=>0],
    ["q"=>"Ghana borders which country to the west?", "a"=>["Côte d'Ivoire","Togo","Burkina Faso"], "correct"=>0],
    ["q"=>"Ghana borders which country to the east?", "a"=>["Togo","Côte d'Ivoire","Mali"], "correct"=>0],
    ["q"=>"Highest mountain in Ghana?", "a"=>["Mount Afadja","Mount Kilimanjaro","Mount Cameroon"], "correct"=>0],
    ["q"=>"National animal of Ghana?", "a"=>["Lion","Elephant","Eagle"], "correct"=>0],
];

if (!isset($_SESSION['step'])) {
    $_SESSION['step'] = 0;
    $_SESSION['score'] = 0;
}

if (!empty($_SESSION['paid']) && !isset($_SESSION['start_time'])) {
    $_SESSION['start_time'] = time();
}

if (isset($_GET['paid']) && $_GET['paid']==1) {
    $_SESSION['paid'] = true;
    $_SESSION['start_time'] = time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer'])) {

    if(empty($_SESSION['paid'])){
        echo "<script>alert('Please pay or unlock via referrals.'); window.location='index.php';</script>";
        exit();
    }

    $current = $_SESSION['step'];
    if ($_POST['answer'] == $questions[$current]['correct']) {
        $_SESSION['score']++;
    }

    $_SESSION['step']++;

    if ($_SESSION['step'] >= count($questions)) {
        $_SESSION['time_taken'] = time() - ($_SESSION['start_time'] ?? time());
        header("Location: ?result");
        exit();
    }

    header("Location: index.php");
    exit();
}

?>
<!DOCTYPE html>
<html>
<head>
<title>AnnkoDeals Quiz</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-light">

<div class="container py-4">

<?php if (isset($_GET['result'])): ?>
    <div class="card p-4 text-center">
        <h3>Your Score: <?= $_SESSION['score'] ?>/20</h3>
        <p>Time taken: <?= $_SESSION['time_taken'] ?> seconds</p>

        <form method="post" action="">
            <input type="hidden" name="save_details" value="1">

            <input class="form-control mb-2" name="name" placeholder="Your name" required>
            <input class="form-control mb-2" name="momo" placeholder="Momo Number" required>

            <label class="d-block mt-3">Referral link: share to qualify</label>

            <!-- CLICK TO COPY REFERRAL -->
            <div class="input-group mb-2" style="max-width:420px; margin:auto;">
                <input id="refLinkInput" type="text" class="form-control" readonly>
                <button class="btn btn-primary" type="button" id="copyBtn">Copy</button>
            </div>
            <small id="copyMsg" class="text-success d-none">Copied!</small>

            <input type="hidden" name="shared" value="1">
            <button class="btn btn-success mt-3 w-100">Submit</button>
        </form>
    </div>

<?php elseif(empty($_SESSION['paid'])): ?>
    <div class="card p-4 text-center">
        <h3>Unlock the Quiz</h3>
        <p>Pay 3 cedis OR refer 10 friends</p>

        <button id="createRefBtn" class="btn btn-primary w-100 mb-3">Generate Referral Link</button>

        <!-- CLICK TO COPY REFERRAL BOX -->
        <div id="refBox" class="d-none">
            <label>Your Referral Link:</label>

            <div class="input-group mb-2" style="max-width:420px; margin:auto;">
                <input id="refLinkInput" type="text" class="form-control" readonly>
                <button class="btn btn-primary" type="button" id="copyBtn">Copy</button>
            </div>

            <small id="copyMsg" class="text-success d-none">Copied!</small>
        </div>

    </div>

<?php else: ?>
    <!-- QUIZ -->
    <div class="card p-4">
        <h4><?= $questions[$_SESSION['step']]['q'] ?></h4>
        <form method="post">
            <?php foreach ($questions[$_SESSION['step']]['a'] as $i=>$ans): ?>
                <button name="answer" value="<?= $i ?>" class="btn btn-outline-primary w-100 my-2">
                    <?= $ans ?>
                </button>
            <?php endforeach; ?>
        </form>
    </div>
<?php endif; ?>

</div>

<script>
// Create referral link
$("#createRefBtn").click(function(){
    $.post("", {create_ref:1}, function(res){
        if(res.ok){
            $("#refBox").removeClass("d-none");
            $("#refLinkInput").val(res.link);
        }
    });
});

// Copy button
$(document).on("click", "#copyBtn", function(){
    const input = document.getElementById("refLinkInput");
    input.select();
    input.setSelectionRange(0,99999);
    navigator.clipboard.writeText(input.value);

    $("#copyMsg").removeClass("d-none");
    setTimeout(()=>$("#copyMsg").addClass("d-none"),1500);
});
</script>

</body>
</html>
