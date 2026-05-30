

<?php
header("Content-Type: application/json");

include 'config.php';

$userMessage = $_POST['message'] ?? '';
$userId = $_POST['user_id'] ?? NULL;

if (empty($userMessage)) {
    echo json_encode(["reply" => "No message provided"]);
    exit;
}

// ==============================
// 🧠 NORMALIZATION
// ==============================
function normalizeMessage($message)
{
    $message = strtolower(trim($message));

    $synonyms = [

        // delete
        "remove" => "delete",
        "erase" => "delete",
        "cancel" => "delete",

        // photos
        "picture" => "photo",
        "image" => "photo",
        "images" => "photo",

        // login
        "signin" => "login",
        "sign in" => "login",
        "log in" => "login",

        // status
        "tracking" => "track",
        "progress" => "status",
        "approve" => "approved",
        "reject" => "rejected",
        "complete" => "completed",
        
        // repair
        "repair" => "fix",
        "fixed" => "fix",
        "resolved" => "fix",

        // account
        "account" => "profile",

        // report
        "complaint" => "report",
        "issue" => "report",

    ];

    foreach ($synonyms as $from => $to) {
        $message = str_replace($from, $to, $message);
    }

    return $message;
}

// ==============================
// 🧠 INTENT DETECTION
// ==============================
function detectIntent($message)
{
    $message = normalizeMessage($message);

    $map = [
        "status" => ["status","track","pending","approved","completed","rejected","progress"],
        "account" => ["login","password","email","profile","register"],
        "location" => ["gps","location","map","marker"],
        "troubleshoot" => ["error","stuck","loading","crash","failed","upload"],
        "report" => ["report","submit","pothole","drainage","street light","traffic light","road sign"],
    ];

    foreach ($map as $intent => $keywords) {
        foreach ($keywords as $word) {
            if (strpos($message, $word) !== false) {
                return $intent;
            }
        }
    }

    return "faq";
}

// ==============================
// 📚 IMPROVED FAQ MATCHING (FIXED CORE)
// ==============================
function getFAQAnswer($conn, $message, $category = null)
{
    $message = normalizeMessage($message);

    // clean words
    $words = preg_split("/\s+/", preg_replace("/[^a-z0-9 ]/", "", $message));

    // remove short words
    $words = array_filter($words, function ($w) {
        return strlen($w) > 2;
    });

    // ================= LOAD FAQ =================
    if ($category) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM faq WHERE category=?");
        mysqli_stmt_bind_param($stmt, "s", $category);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, "SELECT * FROM faq");
    }

    if (!$result) return null;

    $bestScore = 0;
    $bestAnswer = null;

    // strong keywords from your system
    $highValueWords = [
        "delete","edit","report","photo","gps","location",
        "password","login","status","track","reset","submit"
    ];

    while ($row = mysqli_fetch_assoc($result)) {

        $question = strtolower($row['question']);
        $keywords = strtolower($row['keywords'] ?? "");

        $score = 0;

        // =============================
        // 1. KEYWORD FIELD MATCH (MOST IMPORTANT)
        // =============================
        if ($message == strtolower(trim($row['question']))) {
            return $row['answer'];
        }

        if (!empty($keywords)) {
            $kwList = explode(",", $keywords);

            foreach ($kwList as $kw) {
                $kw = trim($kw);

                if ($kw && strpos($message, $kw) !== false) {
                    $score += 40; // VERY STRONG BOOST
                }
            }
        }

        // =============================
        // 2. QUESTION WORD MATCHING
        // =============================
        foreach ($words as $w) {

            if (preg_match('/\b' . preg_quote($w, '/') . '\b/i', $question)) {

                $score += 5;

                if (in_array($w, $highValueWords)) {
                    $score += 15;
                }
            }
        }

        // =============================
        // 3. PARTIAL PHRASE MATCH (BETTER THAN similar_text)
        // =============================
        $cleanQ = preg_replace("/[^a-z0-9 ]/", "", $question);

        $overlap = 0;
        foreach ($words as $w) {
            if (strpos($cleanQ, $w) !== false) {
                $overlap++;
            }
        }

        $score += $overlap * 3;

        // =============================
        // 4. CATEGORY BONUS
        // =============================
        if ($category && $row['category'] == $category) {
            $score += 25;
        }

        // =============================
        // 5. EXACT PHRASE BOOST
        // =============================
        if (strpos($question, $message) !== false) {
            $score += 30;
        }

        // =============================
        // SAVE BEST
        // =============================
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestAnswer = $row['answer'];
        }
    }

    // =============================
    // FINAL THRESHOLD (IMPORTANT FIX)
    // =============================
    if ($bestScore >= 60) {
        return $bestAnswer;
    }

    return null;
}

// ==============================
// 🤖 AI (UNCHANGED)
// ==============================
function callAI($message)
{

    $systemPrompt = "
        You are the official Fix My Road (FMR) intelligent assistant.

        You handle TWO types of questions:

        ========================
        1. FMR SYSTEM SUPPORT
        ========================
        ========================
        🔧 CORE SYSTEM FUNCTIONS
        ========================
        Users can:
        - Report road issues (potholes, drainage, street lights, traffic lights, road signs, roadside safety, public transport facilities)
        - Upload up to 3 photos (photo is required)
        - Submit GPS location (important for accuracy)
        - Track report status in 'My Reports'
        - Manage account (login, register, password reset, profile)

        ========================
        NEARBY REPORTS & MAP
        ========================
        - Users can view nearby reports on map
        - Map shows report locations and statuses
        - Users can filter map by issue type and status
        - User's report can be see by others on map and nearby list once approved by admin but anonymously

        ========================
        📊 REPORT STATUS SYSTEM
        ========================
        Always use and understand these exact statuses:
        - Pending → Report received, waiting for admin review
        - Approved → Report accepted by admin
        - In Progress → Repair work started or scheduled
        - Completed → Issue fully fixed
        - Rejected → Report not accepted (invalid, duplicate, unclear, spam)

        IMPORTANT:
        - Never invent new statuses
        - Always use these exact terms when explaining

        ========================
        🛣️ ROAD ISSUE KNOWLEDGE (IMPORTANT)
        ========================
        You must also explain real-world road infrastructure topics in simple terms:

        Examples:
        - How potholes form (water infiltration, heavy traffic, road fatigue)
        - Why roads crack (temperature change, poor materials, heavy vehicles)
        - Drainage problems (blockage, poor design, flooding)
        - Traffic light failures (electrical issues, system faults)
        - Street light failures (wiring, bulb damage, power issues)

        Keep explanations:
        - Simple
        - Practical
        - Short
        - Easy for normal users to understand

        ========================
        📷 REPORT RULES (STRICT)
        ========================
        - Photo is REQUIRED for every report
        - Maximum 3 photos allowed
        - GPS must be enabled for accuracy
        - Reports cannot be deleted after submission
        - Editing allowed only when status = Pending

        ========================
        📱 APP BEHAVIOR RULES
        ========================
        - Users can track reports in 'My Reports'
        - Notifications are sent when status changes
        - Internet is required for all features
        - Fake reports may be rejected or restricted
        - System is free to use
        - Accounts are required to submit and track reports

        ========================
        📱 PROFILE RULES
        ========================
        - User's email is unique identifier
        - User can change their profile info except email
        - User profile will only seen by admin and used for contact and report management

        ========================
        ⚠️ LOCATION RULES
        ========================
        - GPS may be inaccurate due to weak signal
        - Users can manually adjust map marker
        - Location is only used during report submission and map features
        - Users can report issues in different areas if location is correct

        ========================
        🧠 RESPONSE STYLE
        ========================
        - Be accurate and consistent with FMR system
        - Be concise and user-friendly
        - Do not hallucinate features not in system
        - If unsure, suggest contacting support
        - Combine system guidance + real-world explanation when relevant

        When answering:
        - Be concise
        - Give step-by-step guidance when needed
        - Do NOT invent features that are not mentioned

        ========================
        2. ROAD INFRASTRUCTURE KNOWLEDGE
        ========================
        You can also explain general road engineering topics such as:
        - How potholes form
        - Why roads crack
        - Causes of road damage (rain, heavy vehicles, poor drainage)
        - Maintenance and repair methods

        When answering:
        - Use simple, easy-to-understand explanations
        - Give real-world examples when helpful
        - Keep it short and practical

        ========================
        RULES
        ========================
        - Always stay relevant to roads or FMR system
        - If user asks unrelated topics, politely redirect
        - If unsure, suggest contacting support
        ";

    $apiKey = getenv("GROQ_API_KEY");

    $data = [
        "model" => "openai/gpt-oss-120b",
        "messages" => [
            [
                "role" => "system",
                "content" => $systemPrompt
            ],
            [
                "role" => "user",
                "content" => $message
            ]
        ],
        "temperature" => 0.7,
        "max_tokens" => 300
    ];

    $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ]);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $result = curl_exec($ch);

    $response = json_decode($result, true);

    return $response['choices'][0]['message']['content']
        ?? "AI error.";
}

// ==============================
// MAIN PROCESS
// ==============================
$userMessageLower = strtolower($userMessage);
$intent = detectIntent($userMessageLower);

$categoryMap = [
    "report" => "report",
    "location" => "location",
    "account" => "account",
    "status" => "status",
    "troubleshoot" => "troubleshoot",
    "general" => "general"
];

$category = $categoryMap[$intent] ?? null;

$response = "";

// ================= FAQ =================
if ($intent == "faq") {

    $faqAnswer = getFAQAnswer($conn, $userMessageLower, null);

    if (!$faqAnswer) {
        $faqAnswer = getFAQAnswer($conn, $userMessageLower, "general");
    }

    $response = $faqAnswer ?? "Sorry, I couldn't find an answer in FAQ.";

} else {

    $faqAnswer = getFAQAnswer($conn, $userMessageLower, null);

    if (!$faqAnswer && $category) {
        $faqAnswer = getFAQAnswer($conn, $userMessageLower, $category);
    }
    
    if ($faqAnswer) {
        $response = $faqAnswer;
    } else {
        $response = callAI($userMessage);
    }
}

// ================= OUTPUT =================
echo json_encode([
    "reply" => $response,
    "intent" => $intent,
    "score_system" => "improved_v2"
]);

?>