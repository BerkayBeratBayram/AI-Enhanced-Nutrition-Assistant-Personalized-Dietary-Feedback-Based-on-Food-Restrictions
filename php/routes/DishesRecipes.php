<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
include 'header.php';
include 'db_config.php';
include 'db_connection.php';

$stmt = $conn->prepare("SELECT disease FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$disease = $row['disease'] ?? 'your illness';


$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 10; 
$offset = ($page - 1) * $limit; 

$api_url = "http://127.0.0.1:8000/recommend/?user_id=" . urlencode($user_id) . "&limit=$limit&offset=$offset";
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$recipes = [];
$total_results = 0;
if ($http_code === 200) {
    $data = json_decode($response, true);
    $recipes = $data['recommended_recipes'] ?? [];
    $total_results = $data['total_results'] ?? 0;
}

$total_pages = ceil($total_results / $limit);
?>
<div class="content-wrapper">
    <div class="container-fluid">
        <div class="content-header">
            <div class="row mb-2">
                <div class="col-sm-6">
                <h1 class="m-0">Recipes Suitable for <?= htmlspecialchars(ucfirst($disease)); ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="home.php">Home</a></li>
                        <li class="breadcrumb-item active">Smart Meal Suggestions</li>
                    </ol>
                </div>
            </div>
        </div>
        <input type="hidden" id="userId" value="<?= $_SESSION['user_id']; ?>">

        <div class="card">
            <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap">
    <thead>
        <tr>
            <th>Recipe Name</th>
            <th>Image</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!empty($recipes)): ?>
        <?php foreach ($recipes as $recipe): ?>
            <tr>
                <td><?= htmlspecialchars($recipe['recipe_name']); ?></td>
                <td>
                    <img src="https://media.istockphoto.com/id/1435983029/vector/food-delivery-logo-images.jpg?s=612x612&w=0&k=20&c=HXPxcjOxUiW4pMW1u9E0k2dJYQOU37a_0qZAy3so8fY=" alt="Recipe Image" class="recipe-image">
                </td>
                <td>
                    <button class="btn btn-info btn-sm" onclick="showRecipeDetails(<?= htmlspecialchars(json_encode($recipe)); ?>)">Details</button>
                    <button class="btn btn-success btn-sm" onclick="addToFavorites(<?= $recipe['recipe_id']; ?>)">Add to Favorites</button>                    
                    <button class="btn btn-warning btn-sm" onclick="openRatingModal(<?= $recipe['recipe_id']; ?>)">Rate</button>
    <form action="add_to_daily_meals.php" method="POST" style="display:inline;">
        <input type="hidden" name="recipe_id" value="<?= htmlspecialchars($recipe['recipe_id'], ENT_QUOTES); ?>">
        <button type="submit" class="btn btn-success btn-sm">Add to Daily Meals</button>
    </form>
</td>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="3">No recipes found suitable for your illness.</td>
        </tr>
    <?php endif; ?>
</tbody>

</table>


            </div>
        </div>

        <nav>
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($i === $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?= $i; ?>"><?= $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
</div>

<div id="recipeModalReviews" class="modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="modalTitle" class="modal-title">Recipe Details</h5>
                <button type="button" class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>
</div>
<div id="ratingModal" class="modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rate the Recipe</h5>
                <button type="button" class="close" onclick="closeRatingModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="ratingForm">
                    <div class="form-group">
                        <label>Give Rating (1-5):</label>
                        <div id="starRating" class="star-rating">
                            <span data-value="1" class="star">&#9733;</span>
                            <span data-value="2" class="star">&#9733;</span>
                            <span data-value="3" class="star">&#9733;</span>
                            <span data-value="4" class="star">&#9733;</span>
                            <span data-value="5" class="star">&#9733;</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="ratingComment">Add Comment:</label>
                        <textarea id="ratingComment" class="form-control" rows="4"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRatingModal()">Close</button>
                <button type="button" class="btn btn-success" onclick="submitRating()">Submit</button>
            </div>
        </div>
    </div>
</div>

<script>
function openModal(recipe) {
    const modal = document.getElementById("recipeModalReviews");
    const modalTitle = document.getElementById("modalTitle");
    const modalBody = document.getElementById("modalBody");

    try {
        modalTitle.innerText = recipe.recipe_name;

        modalBody.innerHTML = `
            <strong>Calories:</strong> ${recipe.recipe_calories || 'N/A'}<br>
            <strong>Fat:</strong> ${recipe.recipe_fat_content || 'N/A'}<br>
            <strong>Carbohydrates:</strong> ${recipe.recipe_carbohydrate_content || 'N/A'}<br>
            <strong>Sugar:</strong> ${recipe.recipe_sugar_content || 'N/A'}<br>
            <strong>Protein:</strong> ${recipe.recipe_protein_content || 'N/A'}<br>
            <strong>Instructions:</strong> <p>${recipe.recipe_recipe_instructions || 'N/A'}</p>
        `;

        modal.style.display = "block"; 
    } catch (error) {
        console.error("Error occurred while processing modal data:", error);
    }
}

function closeModal() {
    const modal = document.getElementById("recipeModalReviews");
    modal.style.display = "none"; 
}

let selectedRecipeId = null;

function openRatingModal(recipeId) {
    selectedRecipeId = recipeId; 
    const ratingModal = document.getElementById("ratingModal");
    ratingModal.style.display = "block"; 
}

function closeRatingModal() {
    const ratingModal = document.getElementById("ratingModal");
    ratingModal.style.display = "none"; 
}

function showRecipeDetails(recipe) {
    const modal = document.getElementById("recipeModalReviews");
    const modalTitle = document.getElementById("modalTitle");
    const modalBody = document.getElementById("modalBody");

    try {
        modalTitle.innerText = recipe.recipe_name;

        modalBody.innerHTML = `
            <strong>Calories:</strong> ${recipe.recipe_calories || 'N/A'}<br>
            <strong>Fat:</strong> ${recipe.recipe_fat_content || 'N/A'}<br>
            <strong>Carbohydrates:</strong> ${recipe.recipe_carbohydrate_content || 'N/A'}<br>
            <strong>Sugar:</strong> ${recipe.recipe_sugar_content || 'N/A'}<br>
            <strong>Protein:</strong> ${recipe.recipe_protein_content || 'N/A'}<br>
            <strong>Instructions:</strong> <p>${recipe.recipe_recipe_instructions || 'N/A'}</p>
        `;

        modal.style.display = "block"; 
    } catch (error) {
        console.error("Error occurred while showing recipe details:", error);
    }
}

document.querySelectorAll(".star-rating .star").forEach((star) => {
    star.addEventListener("click", function () {
        document.querySelectorAll(".star-rating .star").forEach((s) => s.classList.remove("selected"));

        const rating = parseInt(this.getAttribute("data-value"));
        for (let i = 1; i <= rating; i++) {
            document.querySelector(`.star-rating .star[data-value="${i}"]`).classList.add("selected");
        }

        window.selectedRating = rating;
        console.log("Selected Star:", rating); 
    });
});

function submitRating() {
    if (!selectedRecipeId) {
        alert("Please select a recipe first.");
        return;
    }

    if (!window.selectedRating) {
        alert("Please select a rating.");
        return;
    }

    const comment = document.getElementById("ratingComment").value.trim();

    if (!comment) {
        alert("Please write a comment.");
        return;
    }

    fetch('http://127.0.0.1:8000/rate_recipe/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            recipe_id: selectedRecipeId,
            user_id: <?= $_SESSION['user_id']; ?>,
            stars: window.selectedRating, 
            comment: comment
        }),
    })
    .then(response => {
        if (!response.ok) {
            throw new Error("An error occurred while submitting the rating.");
        }
        return response.json();
    })
    .then(data => {
        alert(data.message || "Rating submitted successfully!");
        closeRatingModal();
    })
    .catch(error => console.error("Error submitting rating:", error));
}

function addToFavorites(recipeId) {
    const userId = document.getElementById("userId").value;

    console.log("User ID:", userId);
    console.log("Recipe ID:", recipeId);

    if (!userId || !recipeId) {
        alert("User ID or recipe ID not found.");
        return;
    }

    fetch(`http://127.0.0.1:8000/favorites/?user_id=${userId}&recipe_id=${recipeId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error("Error occurred while adding to favorites.");
            }
            return response.json();
        })
        .then((data) => {
            alert(data.message || "Recipe successfully added to favorites!");
        })
        .catch((error) => {
            console.error("Error adding to favorites:", error);
            alert(`Error adding to favorites: ${error.message}`);
        });
}
</script>


<style>
.recipe-image {
    width: 50px; 
    height: 50px; 
    object-fit: cover; 
    border-radius: 5px; 
    border: 1px solid #ddd; 
    display: block;
    margin: 0 auto; 
}

td, th {
    vertical-align: middle; 
    text-align: left; 
    padding: 10px; 
}

.table {
    width: 100%; 
    margin-bottom: 0; 
    border-spacing: 0; 
}

th {
    font-weight: bold; 
    background-color: #f8f9fa; 
}

.card-body {
    padding: 0; 
}

.table-hover tbody tr:hover {
    background-color: #f1f1f1; 
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5); 
    z-index: 1050;
}

.modal-dialog {
    margin: 100px auto;
    max-width: 500px;
}

.star-rating .star {
    font-size: 1.5rem;
    color: #ddd; 
    cursor: pointer;
    transition: color 0.2s ease-in-out;
}

.star-rating .star.selected {
    color: gold; 
}

</style>
