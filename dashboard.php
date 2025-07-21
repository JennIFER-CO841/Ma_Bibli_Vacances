<?php
session_start(); // Démarre la session utilisateur
require_once 'includes/config.php'; // Inclut la configuration de la base de données

// Vérifie si l'utilisateur est connecté, sinon redirige vers la page d'accueil
if (!isset($_SESSION['utilisateur_id'])) {
    header("Location: index.php");
    exit;
}

// Récupère les infos de l'utilisateur depuis la session
$user_id = $_SESSION['utilisateur_id'];
$user_role = $_SESSION['role'];

try {
    // Récupère les emprunts actifs de l'utilisateur connecté
    $stmt_emprunts = $pdo->prepare("
        SELECT e.id AS emprunt_id, l.titre, CONCAT(a.prenom, ' ', a.nom) AS auteur_complete,
               e.date_emprunt, e.date_retour_prevue
        FROM emprunts e
        JOIN livres l ON e.livre_id = l.id
        LEFT JOIN auteurs a ON l.auteur_id = a.id
        WHERE e.utilisateur_id = :user_id
          AND e.statut_emprunt = 'actif'
        ORDER BY e.date_emprunt DESC
    ");
    $stmt_emprunts->execute(['user_id' => $user_id]);
    $emprunts_actifs = $stmt_emprunts->fetchAll();

    // Si l'utilisateur est un admin, récupérer tous les livres
    $livres = [];
    if ($user_role === 'admin') {
        $stmt_livres = $pdo->query("
            SELECT l.titre, l.isbn, l.nombre_exemplaires_disponibles, c.nom_categorie
            FROM livres l
            LEFT JOIN categories c ON l.categorie_id = c.id
            ORDER BY l.titre
        ");
        $livres = $stmt_livres->fetchAll();
    }

} catch (PDOException $e) {
    // En cas d'erreur SQL, arrêter et afficher le message
    die("Erreur : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Tableau de Bord</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Chargement de Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-100 to-blue-200 min-h-screen">

<!-- En-tête de la page -->
<header class="bg-blue-600 text-white px-6 py-4 flex justify-between items-center shadow">
    <h1 class="text-xl font-semibold">Mon Tableau de Bord</h1>
    <nav class="space-x-4">
        <a href="index.php" class="hover:underline font-medium">Accueil</a>
        <a href="logout.php" class="hover:underline font-medium">Déconnexion</a>
    </nav>
</header>

<!-- Contenu principal -->
<main class="max-w-6xl mx-auto mt-8 p-6 bg-white rounded-xl shadow">

    <!-- Section : Emprunts actifs -->
    <h2 class="text-2xl font-bold text-gray-700 mb-4">📖 Mes emprunts actifs</h2>
    <?php if (empty($emprunts_actifs)): ?>
        <p class="text-gray-600">Vous n'avez aucun emprunt actif.</p>
    <?php else: ?>
        <table class="w-full text-left border border-gray-200 rounded-lg shadow-sm">
            <thead class="bg-green-100">
                <tr>
                    <th class="p-3">Titre</th>
                    <th class="p-3">Auteur</th>
                    <th class="p-3">Date d'emprunt</th>
                    <th class="p-3">Retour prévu</th>
                    <th class="p-3">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($emprunts_actifs as $emprunt): ?>
                    <tr class="border-t">
                        <td class="p-3"><?= htmlspecialchars($emprunt['titre']) ?></td>
                        <td class="p-3"><?= htmlspecialchars($emprunt['auteur_complete']) ?></td>
                        <td class="p-3"><?= htmlspecialchars($emprunt['date_emprunt']) ?></td>
                        <td class="p-3"><?= htmlspecialchars($emprunt['date_retour_prevue']) ?></td>
                        <td class="p-3">
                            <!-- Lien pour retourner un livre -->
                            <a href="borrows/return.php?emprunt_id=<?= $emprunt['emprunt_id'] ?>"
                               class="text-blue-600 hover:underline"
                               onclick="return confirm('Confirmer le retour de ce livre ?');">
                               📥 Retourner
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Section visible uniquement pour l'admin -->
    <?php if ($user_role === 'admin'): ?>
        <div class="mt-12">
            <h2 class="text-2xl font-bold text-gray-700 mb-4">📚 Tous les livres</h2>
            <?php if (empty($livres)): ?>
                <p class="text-gray-600">Aucun livre enregistré.</p>
            <?php else: ?>
               <!-- Tableau des livres -->
               <table class="w-full text-left border border-gray-300 text-sm shadow-sm">
                    <thead class="bg-blue-100">
                        <tr>
                            <th class="p-2">Titre</th>
                            <th class="p-2">ISBN</th>
                            <th class="p-2">Catégorie</th>
                            <th class="p-2">Exemplaires disponibles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($livres as $livre): ?>
                            <tr class="border-t">
                                <td class="p-2"><?= htmlspecialchars($livre['titre']) ?></td>
                                <td class="p-2"><?= htmlspecialchars($livre['isbn']) ?></td>
                                <td class="p-2"><?= htmlspecialchars($livre['nom_categorie'] ?? 'N/A') ?></td>
                                <td class="p-2"><?= $livre['nombre_exemplaires_disponibles'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Bouton pour ajouter un livre -->
                <a href="books/add.php"
                   class="inline-block mt-4 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
                    ➕ Ajouter un livre
                </a>
            <?php endif; ?>
        </div>

        <!-- Boutons d'administration -->
        <div class="mt-10">
            <h2 class="text-2xl font-bold text-gray-700 mb-4">🔧 Gestion (Admin)</h2>
            <div class="flex flex-wrap gap-3">
                <a href="gerer_livres.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">📘 Gérer les livres</a>
                <a href="gerer_auteurs.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">👤 Gérer les auteurs</a>
                <a href="gerer_categories.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">📂 Gérer les catégories</a>
                <a href="gerer_utilisateurs.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">🧑‍🤝‍🧑 Gérer les utilisateurs</a>
                <a href="borrows/manage.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">📋 Gérer les emprunts</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Zone spécifique au lecteur -->
    <?php if ($user_role === 'lecteur'): ?>
        <div class="mt-10">
            <h2 class="text-2xl font-bold text-gray-700 mb-4">👤 Lecteur : Mes actions</h2>
            <div class="flex flex-wrap gap-3">
                <a href="gerer_livres.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">📚 Voir tous les livres</a>
                <a href="emprunts.php" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">📖 Historique de mes emprunts</a>
            </div>
        </div>
    <?php endif; ?>

</main>

</body>
</html>
