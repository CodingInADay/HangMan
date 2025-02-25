<?php
header('Content-Type: text/html; charset=UTF-8');

// اتصال به دیتابیس SQLite
$db = new SQLite3('hangman.db');

// ایجاد جدول کلمات
$db->exec("CREATE TABLE IF NOT EXISTS words (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    word TEXT NOT NULL,
    lang TEXT NOT NULL,
    difficulty TEXT NOT NULL
)");

// ایجاد جدول رکوردها
$db->exec("CREATE TABLE IF NOT EXISTS records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    score INTEGER DEFAULT 0
)");

// اضافه کردن کلمات اولیه اگه جدول خالی باشه
$wordCount = $db->querySingle("SELECT COUNT(*) FROM words");
if ($wordCount == 0) {
    $words = [
        'fa' => [
            'easy' => ["نه", "بله", "کتاب", "مداد", "خانه", "آب", "نان", "میز", "در", "پنجره"],
            'medium' => ["مدرسه", "دانشجو", "کارمند", "خیابان", "اتاق", "خانواده", "فروشگاه", "دفتر", "پارک", "ماشین"],
            'hard' => ["دانشگاه", "مستندات", "توسعه‌دهنده", "برنامه‌نویس", "همکاری", "کارخانه", "رابطه‌ها", "پروژه‌ها", "تحقیقات", "مهندسی"]
        ],
        'en' => [
            'easy' => ["cat", "dog", "book", "pen", "home", "car", "bus", "tree", "fish", "bird"],
            'medium' => ["school", "student", "worker", "street", "window", "family", "market", "office", "garden", "animal"],
            'hard' => ["university", "documents", "developer", "programmer", "teamwork", "adventure", "technology", "education", "management", "creativity"]
        ]
    ];
    $stmt = $db->prepare("INSERT INTO words (word, lang, difficulty) VALUES (:word, :lang, :difficulty)");
    foreach ($words as $lang => $levels) {
        foreach ($levels as $difficulty => $wordList) {
            foreach ($wordList as $word) {
                $stmt->bindValue(':word', $word);
                $stmt->bindValue(':lang', $lang);
                $stmt->bindValue(':difficulty', $difficulty);
                $stmt->execute();
            }
        }
    }
}

// زبان پیش‌فرض
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'fa';

// پردازش درخواست‌های AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'getWord') {
            $difficulty = $_POST['difficulty'];
            $stmt = $db->prepare("SELECT word FROM words WHERE lang = :lang AND difficulty = :difficulty ORDER BY RANDOM() LIMIT 1");
            $stmt->bindValue(':lang', $lang);
            $stmt->bindValue(':difficulty', $difficulty);
            $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            echo json_encode(['word' => $result['word']]);
        } elseif ($_POST['action'] === 'saveRecord') {
            $name = $_POST['name'];
            $stmt = $db->prepare("INSERT OR REPLACE INTO records (name, score) VALUES (:name, COALESCE((SELECT score + 1 FROM records WHERE name = :name), 1))");
            $stmt->bindValue(':name', $name);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } elseif ($_POST['action'] === 'getRecords') {
            $result = $db->query("SELECT name, score FROM records");
            $records = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $records[$row['name']] = $row['score'];
            }
            echo json_encode($records);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hangman Game</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #a1c4fd, #c2e9fb);
            color: #2c3e50;
            direction: <?= $lang === 'fa' ? 'rtl' : 'ltr' ?>;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        .container {
            max-width: 700px;
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            text-align: center;
        }
        .header {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        h1 {
            margin: 0;
            font-size: 1.8em;
            color: #34495e;
        }
        select, button {
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            background: #3498db;
            color: white;
            cursor: pointer;
            transition: background 0.3s;
        }
        select:hover, button:hover { background: #2980b9; }
        .controls {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .game-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        .hangman {
            width: 100px;
            height: 120px;
            position: relative;
            margin: 10px auto;
        }
        .hangman div {
            position: absolute;
            transition: all 0.3s;
        }
        .base { width: 80px; height: 5px; bottom: 0; left: 10px; background: #7f8c8d; }
        .pole { width: 5px; height: 100px; bottom: 5px; left: 45px; background: #7f8c8d; }
        .top { width: 40px; height: 5px; top: 0; left: 45px; background: #7f8c8d; }
        .rope { width: 2px; height: 20px; top: 5px; left: 82px; background: #d35400; }
        .head { width: 20px; height: 20px; border-radius: 50%; top: 25px; left: 72px; background: #f1c40f; border: 2px solid #e74c3c; }
        .body { width: 6px; height: 40px; top: 45px; left: 79px; background: #e74c3c; border-radius: 3px; }
        .left-arm { width: 20px; height: 5px; top: 50px; left: 59px; background: #e74c3c; transform: rotate(45deg); border-radius: 2px; }
        .right-arm { width: 20px; height: 5px; top: 50px; left: 79px; background: #e74c3c; transform: rotate(-45deg); border-radius: 2px; }
        .left-leg { width: 20px; height: 5px; top: 85px; left: 59px; background: #e74c3c; transform: rotate(-45deg); border-radius: 2px; }
        .right-leg { width: 20px; height: 5px; top: 85px; left: 79px; background: #e74c3c; transform: rotate(45deg); border-radius: 2px; }

        .word {
            font-size: 1.8em;
            letter-spacing: 8px;
            margin: 15px 0;
            color: #2c3e50;
        }
        .keyboard {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            max-width: 500px;
        }
        .key {
            padding: 8px 12px;
            background: #f1c40f;
            color: #2c3e50;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .key:hover { background: #e67e22; }
        .key:disabled { background: #dcdcdc; cursor: not-allowed; }
        .message { font-size: 1.2em; margin: 10px 0; color: #e74c3c; }
        .records { margin-top: 15px; font-size: 0.9em; color: #34495e; }
        @media (max-width: 600px) {
            .container { padding: 15px; }
            .word { font-size: 1.4em; letter-spacing: 5px; }
            .key { padding: 6px 10px; }
            .hangman { width: 80px; height: 100px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 data-fa="بازی حدس کلمه" data-en="Hangman Game"><?= $lang === 'fa' ? 'بازی حدس کلمه' : 'Hangman Game' ?></h1>
            <select id="language" onchange="window.location.href='?lang='+this.value">
                <option value="fa" <?= $lang === 'fa' ? 'selected' : '' ?>>فارسی</option>
                <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>>English</option>
            </select>
        </div>
        <div class="controls">
            <select id="difficulty">
                <option value="easy" data-fa="ساده" data-en="Easy"><?= $lang === 'fa' ? 'ساده' : 'Easy' ?></option>
                <option value="medium" data-fa="متوسط" data-en="Medium"><?= $lang === 'fa' ? 'متوسط' : 'Medium' ?></option>
                <option value="hard" data-fa="سخت" data-en="Hard"><?= $lang === 'fa' ? 'سخت' : 'Hard' ?></option>
            </select>
            <button onclick="startGame()" data-fa="شروع" data-en="Start"><?= $lang === 'fa' ? 'شروع' : 'Start' ?></button>
        </div>
        <div class="game-area">
            <div class="hangman">
                <div class="base" style="display: none;"></div>
                <div class="pole" style="display: none;"></div>
                <div class="top" style="display: none;"></div>
                <div class="rope" style="display: none;"></div>
                <div class="head" style="display: none;"></div>
                <div class="body" style="display: none;"></div>
                <div class="left-arm" style="display: none;"></div>
                <div class="right-arm" style="display: none;"></div>
                <div class="left-leg" style="display: none;"></div>
                <div class="right-leg" style="display: none;"></div>
            </div>
            <div class="word" id="wordDisplay"></div>
            <div class="message" id="message"></div>
            <div class="keyboard" id="keyboard"></div>
            <button id="restart" onclick="startGame()" style="display: none;" data-fa="بازی دوباره" data-en="Play Again"><?= $lang === 'fa' ? 'بازی دوباره' : 'Play Again' ?></button>
        </div>
        <div class="records" id="records"></div>
    </div>

    <script>
        let currentWord = '';
        let guessedLetters = [];
        let mistakes = 0;
        const maxMistakes = 6;

        function startGame() {
            const lang = document.getElementById('language').value;
            const difficulty = document.getElementById('difficulty').value;
            guessedLetters = [];
            mistakes = 0;
            document.getElementById('message').innerText = '';
            document.getElementById('restart').style.display = 'none';
            updateHangman();

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=getWord&difficulty=${difficulty}`
            })
            .then(response => response.json())
            .then(data => {
                currentWord = data.word;
                updateWordDisplay();
                generateKeyboard(lang);
            });
            updateRecords();
        }

        function updateWordDisplay() {
            const display = currentWord.split('').map(letter => 
                guessedLetters.includes(letter) ? letter : '_'
            ).join(' ');
            document.getElementById('wordDisplay').innerText = display;
            checkWinOrLose();
        }

        function generateKeyboard(lang) {
            const alphabet = lang === 'fa' ? 
                'آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهی'.split('') : 
                'abcdefghijklmnopqrstuvwxyz'.split('');
            const keyboard = document.getElementById('keyboard');
            keyboard.innerHTML = '';
            alphabet.forEach(letter => {
                const btn = document.createElement('button');
                btn.className = 'key';
                btn.innerText = letter;
                btn.onclick = () => guessLetter(letter);
                keyboard.appendChild(btn);
            });
        }

        function guessLetter(letter) {
            if (guessedLetters.includes(letter) || mistakes >= maxMistakes || document.getElementById('wordDisplay').innerText.replace(/ /g, '') === currentWord) return;
            guessedLetters.push(letter);
            const btn = Array.from(document.querySelectorAll('.key')).find(b => b.innerText === letter);
            btn.disabled = true;

            if (!currentWord.includes(letter)) {
                mistakes++;
                updateHangman();
            }
            updateWordDisplay();
        }

        function updateHangman() {
            const parts = ['base', 'pole', 'top', 'rope', 'head', 'body', 'left-arm', 'right-arm', 'left-leg', 'right-leg'];
            parts.forEach((part, index) => {
                const el = document.querySelector(`.${part}`);
                el.style.display = index < mistakes ? 'block' : 'none';
                if (index === mistakes - 1) el.style.transform = 'scale(1.1)';
                setTimeout(() => el.style.transform = 'scale(1)', 300);
            });
        }

        function checkWinOrLose() {
            const currentDisplay = document.getElementById('wordDisplay').innerText.replace(/ /g, '');
            const messageDiv = document.getElementById('message');
            const restartBtn = document.getElementById('restart');
            const lang = document.getElementById('language').value;

            if (currentDisplay === currentWord) {
                messageDiv.innerText = lang === 'fa' ? 'بردی!' : 'You won!';
                const name = prompt(lang === 'fa' ? 'اسمت رو وارد کن:' : 'Enter your name:');
                if (name) {
                    fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=saveRecord&name=${encodeURIComponent(name)}`
                    })
                    .then(() => updateRecords());
                }
                restartBtn.style.display = 'block';
            } else if (mistakes >= maxMistakes) {
                messageDiv.innerText = `${lang === 'fa' ? 'باختی! کلمه:' : 'You lost! Word:'} ${currentWord}`;
                restartBtn.style.display = 'block';
            }
        }

        function updateRecords() {
            const recordsDiv = document.getElementById('records');
            const lang = document.getElementById('language').value;
            recordsDiv.innerHTML = '';

            const title = document.createElement('h2');
            title.innerText = lang === 'fa' ? 'رکوردها' : 'Records';
            recordsDiv.appendChild(title);

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getRecords'
            })
            .then(response => response.json())
            .then(records => {
                if (Object.keys(records).length === 0) {
                    const noRecords = document.createElement('p');
                    noRecords.innerText = lang === 'fa' ? 'هنوز رکوردی نیست!' : 'No records yet!';
                    recordsDiv.appendChild(noRecords);
                } else {
                    for (const [name, score] of Object.entries(records)) {
                        const record = document.createElement('p');
                        record.innerText = `${name}: ${score}`;
                        recordsDiv.appendChild(record);
                    }
                }
            });
        }

        // شروع اولیه
        startGame();
    </script>
</body>
</html>