<?php
set_time_limit(0);
ini_set('memory_limit', '1024M');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Parallel DNS query function
function checkSubdomainsParallel(array $subs, string $domain): array {
    $mh = curl_multi_init();
    $curlHandles = [];
    $results = [];

    foreach ($subs as $sub) {
        $fqdn = $sub . '.' . $domain;
        $url = "https://cloudflare-dns.com/dns-query?name=" . urlencode($fqdn) . "&type=A";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/dns-json',
            'user-agent: Mozilla/5.0 (SubdomainScanner)'
        ]);
        curl_multi_add_handle($mh, $ch);
        $curlHandles[$fqdn] = $ch;
    }

    do {
        $status = curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0 && $status == CURLM_OK);

    foreach ($curlHandles as $fqdn => $ch) {
        $response = curl_multi_getcontent($ch);
        $data = json_decode($response, true);

        $found = false;
        if (isset($data['Answer']) && is_array($data['Answer'])) {
            foreach ($data['Answer'] as $answer) {
                if (in_array($answer['type'], [1, 5])) { // A=1, CNAME=5
                    $found = true;
                    break;
                }
            }
        }

        $results[$fqdn] = $found;

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }

    curl_multi_close($mh);

    return $results;
}

// Combination generating generator function
function generate_combos_iter($charset, $min, $max) {
    $lenCharset = strlen($charset);
    for ($length = $min; $length <= $max; $length++) {
        $total = pow($lenCharset, $length);
        for ($i = 0; $i < $total; $i++) {
            $num = $i;
            $combo = '';
            for ($j = 0; $j < $length; $j++) {
                $combo = $charset[$num % $lenCharset] . $combo;
                $num = intdiv($num, $lenCharset);
            }
            yield $combo;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'stop') {
        file_put_contents('stop.txt', '1');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'stopping']);
        exit;
    }

    $domain = trim($_POST['domain'] ?? '');
    $minLen = max(1, intval($_POST['minlen'] ?? 1));
    $maxLen = max($minLen, intval($_POST['maxlen'] ?? 2));
    $charset = $_POST['charset'] ?? 'abcdefghijklmnopqrstuvwxyz0123456789';
    $limit = max(0, intval($_POST['limit'] ?? 0));
    $batchSize = 10; // Reduced batch size for more frequent stop checks

    // Clear stop flag if starting new scan
    if (file_exists('stop.txt')) {
        unlink('stop.txt');
    }

    $file = fopen("results.txt", "a");
    if (!$file) {
        die("Results file could not be opened.");
    }
    fwrite($file, "===== " . date('Y-m-d H:i:s') . " - Scan Started: {$domain} =====\n");

    echo "<!doctype html><html><head><meta charset='utf-8'><title>NullSecurityX Subdomain Scanner</title>";
    // CSS
    echo <<<CSS
<style>
    @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=VT323&display=swap');

    * { box-sizing: border-box; }
    body { 
        font-family: 'VT323', monospace; 
        background: #0a0a0a; 
        color: #4CAF50; 
        margin: 0; 
        padding: 20px; 
        min-height: 100vh;
        overflow-x: hidden;
        position: relative;
    }
    .header {
        text-align: center;
        margin-bottom: 30px;
        position: relative;
    }
    .logo {
        width: 120px;
        height: auto;
        border-radius: 50%;
        box-shadow: 0 2px 10px rgba(76, 175, 80, 0.2);
        margin-bottom: 15px;
        transition: all 0.3s ease;
    }
    .logo:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
    }
    h1 { 
        font-family: 'Orbitron', monospace;
        color: #4CAF50; 
        font-size: 2.5em; 
        margin: 0; 
        letter-spacing: 3px;
        text-transform: uppercase;
    }
    h2 { 
        font-family: 'Orbitron', monospace;
        color: #4CAF50; 
        text-align: center;
        letter-spacing: 1px;
        font-size: 1.5em;
    }
    #status {
        text-align: center;
        color: #4CAF50;
        font-size: 18px;
        margin: 10px 0;
        font-weight: bold;
    }
    #progressContainer {
        width: 100%;
        height: 6px;
        background: rgba(76, 175, 80, 0.1);
        border: 1px solid #4CAF50;
        border-radius: 3px;
        margin: 10px 0;
        overflow: hidden;
        box-shadow: inset 0 0 5px rgba(76, 175, 80, 0.1);
    }
    #progressBar {
        height: 100%;
        background: linear-gradient(90deg, #4CAF50, #45a049);
        width: 0%;
        transition: width 0.3s ease;
        border-radius: 3px;
    }
    #results, #foundList {
        background: rgba(10,10,10,0.9); 
        border: 1px solid #4CAF50;
        border-radius: 4px;
        box-shadow: 0 2px 10px rgba(76, 175, 80, 0.1);
        max-height: 400px; 
        overflow-y: auto; 
        padding: 20px; 
        font-family: 'VT323', monospace; 
        font-size: 16px;
        margin-bottom: 10px;
    }
    #results { 
        color: #81C784; 
        width: 48%; 
        float: left; 
        margin-right: 4%; 
        border-left: 3px solid #81C784;
    }
    #foundList { 
        color: #FFEB3B; 
        font-weight: bold; 
        width: 48%; 
        float: left; 
        border-left: 3px solid #FFEB3B;
    }
    .trying { 
        color: #81C784; 
        padding: 5px 0;
        border-bottom: 1px dashed #81C784;
        opacity: 0;
        animation: fadeIn 0.5s ease forwards;
        white-space: pre;
    }
    .found { 
        color: #FFEB3B; 
        font-weight: bold; 
        padding: 5px 0;
        border-bottom: 1px solid #FFEB3B;
        animation: fadeIn 0.5s ease forwards;
        white-space: pre;
    }
    @keyframes fadeIn {
        to { opacity: 1; }
    }
    #stopBtnContainer {
        text-align: center;
        margin: 20px 0;
    }
    #stopBtn {
        background: linear-gradient(135deg, #f44336, #d32f2f);
        color: #fff;
        padding: 12px 30px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 700;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(244, 67, 54, 0.2);
        font-family: 'Orbitron', monospace;
        text-transform: uppercase;
        letter-spacing: 1px;
        display: inline-block;
    }
    #stopBtn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(244, 67, 54, 0.3);
    }
    #stopBtn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    #container {
        overflow: hidden; /* float clear */
        margin-top: 20px;
        clear: both;
    }
    form {
        background: rgba(10,10,10,0.9); 
        padding: 30px; 
        border: 1px solid #4CAF50;
        border-radius: 4px;
        box-shadow: 0 2px 10px rgba(76, 175, 80, 0.1); 
        max-width: 450px; 
        margin: 0 auto 20px; 
    }
    label { 
        display: block; 
        margin-bottom: 15px; 
        font-weight: bold; 
        color: #4CAF50;
        font-size: 16px;
    }
    input[type=text], input[type=number] {
        width: 100%; 
        padding: 12px; 
        margin-top: 5px;
        border: 1px solid #4CAF50; 
        border-radius: 4px; 
        font-size: 16px;
        transition: all 0.3s ease;
        box-sizing: border-box;
        background: #000;
        color: #4CAF50;
    }
    input[type=text]:focus, input[type=number]:focus {
        border-color: #81C784;
        outline: none;
        box-shadow: 0 0 5px rgba(129, 199, 132, 0.3);
        color: #81C784;
    }
    input[type=text]::placeholder, input[type=number]::placeholder {
        color: #4CAF50;
        opacity: 0.5;
    }
    button {
        background: linear-gradient(135deg, #4CAF50, #45a049); 
        color: #fff; 
        padding: 14px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer; 
        font-size: 16px; 
        font-weight: 700;
        transition: all 0.3s ease;
        width: 100%;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-family: 'Orbitron', monospace;
        box-shadow: 0 2px 5px rgba(76, 175, 80, 0.2);
    }
    button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(76, 175, 80, 0.3);
        background: #4CAF50;
    }
    @media(max-width: 700px) {
        #results, #foundList {
            width: 100%; 
            margin-right: 0; 
            margin-bottom: 15px;
            max-height: 250px;
        }
        #container { 
            overflow: visible; 
        }
        #stopBtn {
            width: 200px;
        }
        h1 { font-size: 2em; }
        .logo { width: 80px; }
    }
</style>
CSS;

    // JS
    echo <<<JS
<script>
    let totalLimit = {$limit};
    let isScanning = true;
    function scrollToBottom(id) {
        var el = document.getElementById(id);
        el.scrollTop = el.scrollHeight;
    }
    function addLine(type, text) {
        var results = document.getElementById('results');
        var div = document.createElement('div');
        div.className = type;
        div.textContent = text;
        results.appendChild(div);
        scrollToBottom('results');
    }
    function addFound(text) {
        var foundList = document.getElementById('foundList');
        var div = document.createElement('div');
        div.textContent = text;
        foundList.appendChild(div);
        scrollToBottom('foundList');
    }
    function updateStatus(count) {
        var status = document.getElementById('status');
        if (totalLimit > 0) {
            var perc = Math.min((count / totalLimit) * 100, 100);
            document.getElementById('progressBar').style.width = perc + '%';
            status.textContent = 'Scanned: ' + count + ' / ' + totalLimit + ' (' + Math.round(perc) + '%)';
        } else {
            status.textContent = 'Scanned: ' + count + ' (Unlimited mode)';
        }
    }
    function stopScan() {
        if (!isScanning) return;
        var btn = document.getElementById('stopBtn');
        btn.disabled = true;
        btn.textContent = 'Stopping...';
        isScanning = false;
        fetch(window.location.href, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=stop'
        }).then(response => response.json())
          .then(data => {
              if (data.status === 'stopping') {
                  addLine('trying', '[*] Stopping scan... Please wait for current batch to finish.');
              }
          }).catch(error => {
              console.error('Stop request error:', error);
              addLine('trying', '[*] Stop signal sent. Waiting for scan to halt.');
          });
    }
</script>
JS;

    echo "</head><body>";
    echo "<div class='header'>";
    echo "<img src='https://pbs.twimg.com/profile_images/1973553823932108800/wkVyroLh.jpg' alt='NullSecurityX Logo' class='logo'>";
    echo "<h1>NullSecurityX Subdomain Scanner</h1>";
    echo "</div>";
    echo "<h2>Scan Started: {$domain}</h2>";
    echo "<div id='status'>Scanned: 0</div>";
    echo "<div id='progressContainer'><div id='progressBar'></div></div>";
    echo "<div id='stopBtnContainer'>";
    echo "<button id='stopBtn' onclick='stopScan()'>🛑 Stop Scan</button>";
    echo "</div>";
    echo "<div id='container'>";
    echo "<div id='results'></div>";
    echo "<div id='foundList'></div>";
    echo "</div>";

    @ob_end_flush();
    @ob_implicit_flush(true);
    echo str_repeat(' ', 1024);
    flush();

    $generator = generate_combos_iter($charset, $minLen, $maxLen);
    $count = 0;
    $batch = [];
    $stopped = false;

    foreach ($generator as $sub) {
        if (file_exists('stop.txt')) {
            echo "<script>addLine('trying', '[*] Scan stopped by user.');</script>\n";
            flush();
            $stopped = true;
            break;
        }
        if ($limit > 0 && $count >= $limit) break;
        $batch[] = $sub;
        $count++;
        echo "<script>updateStatus({$count});</script>\n";
        flush();

        if (count($batch) === $batchSize) {
            // Additional check before processing batch
            if (file_exists('stop.txt')) {
                echo "<script>addLine('trying', '[*] Stop detected, skipping current batch.');</script>\n";
                flush();
                $stopped = true;
                $batch = [];
                break;
            }

            echo "<script>addLine('trying', '[*] Scanning group: " . htmlspecialchars(implode(", ", $batch)) . "');</script>\n";
            flush();

            $results = checkSubdomainsParallel($batch, $domain);

            foreach ($results as $fqdn => $found) {
                // Check stop even during result processing (though unlikely to change mid-loop)
                if (file_exists('stop.txt')) {
                    $stopped = true;
                    break 2; // Break both foreach and outer if
                }
                if ($found) {
                    echo "<script>addLine('found', '[+] Found: {$fqdn}');</script>\n";
                    echo "<script>addFound('{$fqdn}');</script>\n";
                    fwrite($file, $fqdn . "\n");
                } else {
                    echo "<script>addLine('trying', '[-] Not found: {$fqdn}');</script>\n";
                }
                flush();
            }

            $batch = [];
        }
    }

    // Process remaining batch if not stopped
    if (!$stopped && count($batch) > 0) {
        // Final check
        if (file_exists('stop.txt')) {
            echo "<script>addLine('trying', '[*] Scan stopped by user.');</script>\n";
            flush();
            $stopped = true;
        } else {
            echo "<script>addLine('trying', '[*] Scanning last group: " . htmlspecialchars(implode(", ", $batch)) . "');</script>\n";
            flush();

            $results = checkSubdomainsParallel($batch, $domain);

            foreach ($results as $fqdn => $found) {
                if ($found) {
                    echo "<script>addLine('found', '[+] Found: {$fqdn}');</script>\n";
                    echo "<script>addFound('{$fqdn}');</script>\n";
                    fwrite($file, $fqdn . "\n");
                } else {
                    echo "<script>addLine('trying', '[-] Not found: {$fqdn}');</script>\n";
                }
                flush();
            }
        }
    }

    if ($stopped) {
        fwrite($file, "===== Scan stopped by user =====\n\n");
        echo "<script>addLine('trying', '[*] Scan stopped. Total attempted: {$count}.');</script>";
        echo "<script>document.getElementById('stopBtn').textContent = 'Stopped';</script>";
    } else {
        fwrite($file, "===== Scan completed =====\n\n");
        echo "<script>addLine('trying', '[*] Scan completed. Total: {$count} attempted.');</script>";
        echo "<script>document.getElementById('stopBtn').style.display = 'none';</script>";
    }
    fclose($file);

    // Clear stop flag
    if (file_exists('stop.txt')) {
        unlink('stop.txt');
    }

    echo "</body></html>";
    exit;
}
?>

<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>NullSecurityX Subdomain Scanner</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=VT323&display=swap');

    * { box-sizing: border-box; }
    body { 
        font-family: 'VT323', monospace; 
        background: #0a0a0a; 
        color: #4CAF50; 
        margin: 0; 
        padding: 20px; 
        min-height: 100vh;
        overflow-x: hidden;
        position: relative;
    }
    .header {
        text-align: center;
        margin-bottom: 30px;
        position: relative;
    }
    .logo {
        width: 120px;
        height: auto;
        border-radius: 50%;
        box-shadow: 0 2px 10px rgba(76, 175, 80, 0.2);
        margin-bottom: 15px;
        transition: all 0.3s ease;
    }
    .logo:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
    }
    h1 { 
        font-family: 'Orbitron', monospace;
        color: #4CAF50; 
        font-size: 2.5em; 
        margin: 0; 
        letter-spacing: 3px;
        text-transform: uppercase;
    }
    h2 { 
        font-family: 'Orbitron', monospace;
        color: #4CAF50; 
        text-align: center;
        letter-spacing: 1px;
        font-size: 1.5em;
    }
    form {
      background: rgba(10,10,10,0.9); 
      padding: 30px; 
      border: 1px solid #4CAF50;
      border-radius: 4px;
      box-shadow: 0 2px 10px rgba(76, 175, 80, 0.1); 
      max-width: 450px; 
      margin: 0 auto; 
      backdrop-filter: blur(5px);
    }
    label { 
        display: block; 
        margin-bottom: 15px; 
        font-weight: bold; 
        color: #4CAF50;
        font-size: 16px;
    }
    input[type=text], input[type=number] {
      width: 100%; 
      padding: 12px; 
      margin-top: 5px;
      border: 1px solid #4CAF50; 
      border-radius: 4px; 
      font-size: 16px;
      transition: all 0.3s ease;
      box-sizing: border-box;
      background: #000;
      color: #4CAF50;
    }
    input[type=text]:focus, input[type=number]:focus {
      border-color: #81C784;
      outline: none;
      box-shadow: 0 0 5px rgba(129, 199, 132, 0.3);
      color: #81C784;
    }
    input[type=text]::placeholder, input[type=number]::placeholder {
        color: #4CAF50;
        opacity: 0.5;
    }
    button {
      background: linear-gradient(135deg, #4CAF50, #45a049); 
      color: #fff; 
      padding: 14px 20px;
      border: none;
      border-radius: 4px;
      cursor: pointer; 
      font-size: 16px; 
      font-weight: 700;
      transition: all 0.3s ease;
      width: 100%;
      text-transform: uppercase;
      letter-spacing: 1px;
      font-family: 'Orbitron', monospace;
      box-shadow: 0 2px 5px rgba(76, 175, 80, 0.2);
    }
    button:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(76, 175, 80, 0.3);
      background: #4CAF50;
    }
    @media(max-width: 700px) {
        h1 { font-size: 2em; }
        .logo { width: 80px; }
    }
  </style>
</head>
<body>
  <div class="header">
    <img src="https://pbs.twimg.com/profile_images/1973553823932108800/wkVyroLh.jpg" alt="NullSecurityX Logo" class="logo">
    <h1>NullSecurityX Subdomain Scanner</h1>
  </div>
  <form method="post" autocomplete="off">
    <label>Domain:
      <input type="text" name="domain" required placeholder="example.com">
    </label>
    <label>Charset:
      <input type="text" name="charset" value="abcdefghijklmnopqrstuvwxyz0123456789">
    </label>
    <label>Min Len:
      <input type="number" name="minlen" value="1" min="1">
    </label>
    <label>Max Len:
      <input type="number" name="maxlen" value="2" min="1">
    </label>
    <label>Limit (0 = unlimited):
      <input type="number" name="limit" value="0" min="0">
    </label>
    <button type="submit">Start Scan</button>
  </form>
</body>
</html>