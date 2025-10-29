<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
include 'header.php';
include 'db_config.php';

$api_url = "http://127.0.0.1:8000/daily_meals/?user_id=" . urlencode($user_id);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$daily_meals = [];
$total_calories = 0;
$total_fat = 0;
$total_protein = 0;
$total_carbohydrates = 0;

if ($response) {
    $data = json_decode($response, true);
    if (isset($data['daily_meals'])) {
        $daily_meals = $data['daily_meals'];

        foreach ($daily_meals as $meal) {
            $total_calories += $meal['recipe_calories'];
            $total_fat += $meal['recipe_fat_content'];
            $total_protein += $meal['recipe_protein_content'];
            $total_carbohydrates += $meal['recipe_carbohydrate_content'];
        }
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0">My Daily Bites</h1>
        </div>
    </div>
    <div class="content">
        <table class="table table-striped">
            <thead>
                <tr>
                <th>Recipe Name</th>
                <th>Calories</th>
                <th>Fat</th>
                <th>Protein</th>
                <th>Carbohydrates</th>
                <th>Action</th>

                </tr>
            </thead>
            <tbody>
                <?php if (!empty($daily_meals)): ?>
                    <?php foreach ($daily_meals as $meal): ?>
                        <tr>
                            <td><?= htmlspecialchars($meal['recipe_name'], ENT_QUOTES); ?></td>
                            <td><?= htmlspecialchars($meal['recipe_calories'], ENT_QUOTES); ?> kcal</td>
                            <td><?= htmlspecialchars($meal['recipe_fat_content'], ENT_QUOTES); ?> g</td>
                            <td><?= htmlspecialchars($meal['recipe_protein_content'], ENT_QUOTES); ?> g</td>
                            <td><?= htmlspecialchars($meal['recipe_carbohydrate_content'], ENT_QUOTES); ?> g</td>
                            <td>
                                <button type="button" class="btn btn-info btn-sm" onclick="showRecipeDetails(<?= htmlspecialchars(json_encode($meal), ENT_QUOTES); ?>)">Details</button>
                                <button type="button" class="btn btn-success btn-sm" onclick="addToFavorites(<?= htmlspecialchars($meal['recipe_id'], ENT_QUOTES); ?>)">Add to Favorites</button>
                                <button type="button" class="btn btn-warning btn-sm" onclick="openRatingModal(<?= htmlspecialchars($meal['recipe_id'], ENT_QUOTES); ?>)">Review</button>
                                <form action="remove_from_daily_meals.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="recipe_id" value="<?= htmlspecialchars($meal['recipe_id'], ENT_QUOTES); ?>">
                                    <input type="hidden" id="userId" value="<?= $_SESSION['user_id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No meal record found for today.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <!-- Total Values ​​-->
            <?php if (!empty($daily_meals)): ?>
                <tfoot>
                    <tr>
                        <td><strong>Total</strong></td>
                        <td><strong><?= htmlspecialchars($total_calories); ?> kcal</strong></td>
                        <td><strong><?= htmlspecialchars($total_fat); ?> g</strong></td>
                        <td><strong><?= htmlspecialchars($total_protein); ?> g</strong></td>
                        <td><strong><?= htmlspecialchars($total_carbohydrates); ?> g</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
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
                <h5 class="modal-title">Review Recipe</h5>
                <button type="button" class="close" onclick="closeRatingModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="ratingForm">
                    <div class="form-group">
                        <label>Rate (1-5):</label>
                        <div id="starRating" class="star-rating">
                            <span data-value="1" class="star">&#9733;</span>
                            <span data-value="2" class="star">&#9733;</span>
                            <span data-value="3" class="star">&#9733;</span>
                            <span data-value="4" class="star">&#9733;</span>
                            <span data-value="5" class="star">&#9733;</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="ratingComment">Make Comment:</label>
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
            <strong>Recipe:</strong> <p>${recipe.recipe_recipe_instructions || 'N/A'}</p>
        `;

        modal.style.display = "block"; 
    } catch (error) {
        console.error("Modal verisi işlenirken hata oluştu:", error);
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
            <strong>Recipe:</strong> <p>${recipe.recipe_recipe_instructions || 'N/A'}</p>
        `;

        modal.style.display = "block"; 
    } catch (error) {
        console.error("Tarif detayları gösterilirken bir hata oluştu:", error);
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
        alert("Please select a point.");
        return;
    }

    const comment = document.getElementById("ratingComment").value.trim();

    if (!comment) {
        alert("Please make a comment:");
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
            throw new Error("An error occurred while submitting a review.");
        }
        return response.json();
    })
    .then(data => {
        alert(data.message || "Review submitted successfully!");
        closeRatingModal();
    })
    .catch(error => console.error("Error sending review:", error));
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
                throw new Error("An error occurred while adding to favorites.");
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
