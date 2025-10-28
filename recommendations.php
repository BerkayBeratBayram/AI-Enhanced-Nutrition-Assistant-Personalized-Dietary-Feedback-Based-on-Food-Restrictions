<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$api_url = "http://127.0.0.1:8000/recommend/?user_id=" . urlencode($user_id);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$recipes = [];
$message = "No recipes available.";

if ($http_code === 200 && $response) {
    $data = json_decode($response, true);
    if (isset($data['recommended_recipes']) && !empty($data['recommended_recipes'])) {
        $recipes = $data['recommended_recipes'];
        $message = "Here are your recommended recipes!";
    } elseif (isset($data['message'])) {
        $message = $data['message'];
    }
}

$favorites_url = "http://127.0.0.1:8000/favorites/?user_id=" . urlencode($user_id);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $favorites_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$favorites_response = curl_exec($ch);
curl_close($ch);

$favorites = [];
if ($favorites_response) {
    $favorites_data = json_decode($favorites_response, true);
    if (isset($favorites_data['favorites']) && !empty($favorites_data['favorites'])) {
        $favorites = array_column($favorites_data['favorites'], 'recipe_id');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Recommendations</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h1, h2 {
            color: #333;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        li {
            margin: 10px 0;
            padding: 10px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .remove {
            background-color: #f44336;
        }
        .remove:hover {
            background-color: #d32f2f;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            padding-top: 60px;
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            border-radius: 10px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .favorites-link {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <a href="favorites.php" class="favorites-link">View Favorites</a>

    <h1>Recommended Recipes</h1>
    <p><?= htmlspecialchars($message); ?></p>

    <ul>
        <?php foreach ($recipes as $recipe): ?>
            <li>
                <button onclick="fetchRecipeDetails(<?= htmlspecialchars(json_encode($recipe)); ?>)">
                    <?= htmlspecialchars($recipe['recipe_name']); ?>
                </button>
                <?php if (in_array($recipe['recipe_id'], $favorites)): ?>
                    <button id="favorite-btn-<?= $recipe['recipe_id']; ?>" 
                            class="remove"
                            onclick="toggleFavorite(<?= $user_id; ?>, <?= $recipe['recipe_id']; ?>, false)">
                        ❌ Remove from Favorites
                    </button>
                <?php else: ?>
                    <button id="favorite-btn-<?= $recipe['recipe_id']; ?>" 
                            onclick="toggleFavorite(<?= $user_id; ?>, <?= $recipe['recipe_id']; ?>, true)">
                        ⭐ Add to Favorites
                    </button>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <div id="recipeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="recipeName"></h2>
            <p id="recipeInstructions"></p>
            <p><strong>Calories:</strong> <span id="calories"></span> kcal</p>
            <p><strong>Fat Content:</strong> <span id="fatContent"></span> g</p>
            <p><strong>Sugar Content:</strong> <span id="sugarContent"></span> g</p>
            <p><strong>Protein Content:</strong> <span id="proteinContent"></span> g</p>
            <p><strong>Carbohydrate Content:</strong> <span id="carbohydrateContent"></span> g</p>
        </div>
    </div>

    <script>
        function fetchRecipeDetails(recipe) {
    const modal = document.getElementById("recipeModal");
    document.getElementById("recipeName").textContent = recipe.recipe_name;
    document.getElementById("recipeInstructions").textContent = recipe.recipe_recipe_instructions || "No instructions available.";
    document.getElementById("calories").textContent = recipe.recipe_calories || "N/A";
    document.getElementById("fatContent").textContent = recipe.recipe_fat_content || "N/A";
    document.getElementById("sugarContent").textContent = recipe.recipe_sugar_content || "N/A";
    document.getElementById("proteinContent").textContent = recipe.recipe_protein_content || "N/A";
    document.getElementById("carbohydrateContent").textContent = recipe.recipe_carbohydrate_content || "N/A";
    modal.style.display = "block";
}


        function closeModal() {
            const modal = document.getElementById("recipeModal");
            modal.style.display = "none";
        }

        function toggleFavorite(userId, recipeId, isAdding) {
            const buttonId = `favorite-btn-${recipeId}`;
            const favoriteButton = document.getElementById(buttonId);
            const endpoint = isAdding 
                ? `http://127.0.0.1:8000/favorites/?user_id=${userId}&recipe_id=${recipeId}` 
                : `http://127.0.0.1:8000/favorites/?user_id=${userId}&recipe_id=${recipeId}`;
            const method = isAdding ? 'POST' : 'DELETE';

            fetch(endpoint, { method })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (isAdding) {
                        favoriteButton.textContent = "❌ Remove from Favorites";
                        favoriteButton.classList.add("remove");
                        favoriteButton.setAttribute("onclick", `toggleFavorite(${userId}, ${recipeId}, false)`);
                    } else {
                        favoriteButton.textContent = "⭐ Add to Favorites";
                        favoriteButton.classList.remove("remove");
                        favoriteButton.setAttribute("onclick", `toggleFavorite(${userId}, ${recipeId}, true)`);
                    }
                })
                .catch(error => {
                    console.error("Error toggling favorite:", error);
                    alert("Failed to update favorites.");
                });
        }
    </script>
</body>
</html>
