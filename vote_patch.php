<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

// Vérifier que la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['patch_id']) || !isset($data['vote_type'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$patch_id = (int)$data['patch_id'];
$vote_type = $data['vote_type'];

// Valider le type de vote
if (!in_array($vote_type, ['upvote', 'downvote'])) {
    echo json_encode(['success' => false, 'message' => 'Type de vote invalide']);
    exit;
}

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour voter']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

try {
    // Vérifier si la patch note existe
    $stmt = $pdo->prepare("SELECT id FROM patch_notes WHERE id = ?");
    $stmt->execute([$patch_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Patch note introuvable']);
        exit;
    }

    // Vérifier si l'utilisateur a déjà voté pour ce patch note
    $stmt = $pdo->prepare("SELECT id FROM patch_votes WHERE user_id = ? AND patch_id = ?");
    $stmt->execute([$user_id, $patch_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Vous avez déjà voté pour cette note']);
        exit;
    }

    // Mettre à jour le vote
    $column = $vote_type === 'upvote' ? 'upvotes' : 'downvotes';
    $stmt = $pdo->prepare("UPDATE patch_notes SET $column = $column + 1 WHERE id = ?");
    $stmt->execute([$patch_id]);

    // Enregistrer le vote dans la table patch_votes
    $stmt = $pdo->prepare("INSERT INTO patch_votes (user_id, patch_id, vote_type) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $patch_id, $vote_type]);

    echo json_encode(['success' => true, 'message' => 'Vote enregistré']);

} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
