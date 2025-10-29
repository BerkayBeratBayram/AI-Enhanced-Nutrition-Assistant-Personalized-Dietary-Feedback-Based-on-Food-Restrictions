<?php
include 'auth.php'; 

$user_id = $_SESSION['user_id'];

$api_url = "http://127.0.0.1:8000/favorites/?user_id=" . urlencode($user_id);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$favorites = [];
if ($response) {
    $data = json_decode($response, true);
    if (isset($data['favorites'])) {
        $favorites = $data['favorites'];
    }
}

include 'header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Favorite Recipes</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="home.php">Home</a></li>
                        <li class="breadcrumb-item active">Favorite Recipes</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="content">
    <div class="container-fluid">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Recipe Name</th>
                    <th>Instructions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($favorites)): ?>
                    <?php foreach ($favorites as $favorite): ?>
                        <tr>
                            <td><?= htmlspecialchars($favorite['recipe_name'], ENT_QUOTES); ?></td>
                            <td><?= htmlspecialchars(substr($favorite['recipe_recipe_instructions'], 0, 50)) . '...'; ?></td>
                            <td>
    <button type="button" class="btn btn-info btn-sm" onclick="openFavoriteModal(<?= htmlspecialchars(json_encode($favorite)); ?>)">
        Details
    </button>
    
    <button type="button" class="btn btn-warning btn-sm" onclick="openRatingModal('<?= htmlspecialchars($favorite['recipe_id'], ENT_QUOTES); ?>')">
        Rate
    </button>
    
    <form action="remove_from_favorites.php" method="POST" style="display:inline;">
        <input type="hidden" name="recipe_id" value="<?= htmlspecialchars($favorite['recipe_id'], ENT_QUOTES); ?>">
        <button type="submit" class="btn btn-danger btn-sm">Remove</button>
    </form>
</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">No recipes have been added to favorites.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="favoriteRecipeModal" class="modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="favoriteModalTitle" class="modal-title">Recipe Details</h5>
                <button type="button" class="close" onclick="closeFavoriteModal()">&times;</button>
            </div>
            <div class="modal-body" id="favoriteModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeFavoriteModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<div id="ratingModal" class="modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rate Recipe</h5>
                <button type="button" class="close" onclick="closeRatingModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Please add your rating and comment for the recipe:</p>
                <div class="star-rating">
                <div class="star-rating">
                    <span class="star" data-value="1">&#9733;</span>
                    <span class="star" data-value="2">&#9733;</span>
                    <span class="star" data-value="3">&#9733;</span>
                    <span class="star" data-value="4">&#9733;</span>
                    <span class="star" data-value="5">&#9733;</span>
                </div>

                </div>
                <textarea id="ratingComment" class="form-control" placeholder="Write your comment"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="submitRating()">Submit</button>
                <button type="button" class="btn btn-secondary" onclick="closeRatingModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function openFavoriteModal(recipe) {
    const modal = document.getElementById("favoriteRecipeModal");
    const modalTitle = document.getElementById("favoriteModalTitle");
    const modalBody = document.getElementById("favoriteModalBody");

    try {
        modalTitle.innerText = recipe.recipe_name;
        modalBody.innerHTML = `
            <strong>Calories:</strong> ${recipe.recipe_calories || 'N/A'} <br>
            <strong>Fat:</strong> ${recipe.recipe_fat_content || 'N/A'} <br>
            <strong>Carbohydrates:</strong> ${recipe.recipe_carbohydrate_content || 'N/A'} <br>
            <strong>Sugar:</strong> ${recipe.recipe_sugar_content || 'N/A'} <br>
            <strong>Protein:</strong> ${recipe.recipe_protein_content || 'N/A'} <br>
            <strong>Instructions:</strong> <p>${recipe.recipe_recipe_instructions || 'N/A'}</p>
        `;
        modal.style.display = "block"; 
    } catch (error) {
        console.error("Error processing modal data:", error);
    }
}

function closeFavoriteModal() {
    const modal = document.getElementById("favoriteRecipeModal");
    modal.style.display = "none"; 
}

function openRatingModal(recipeId) {
    if (!recipeId) {
        alert("A recipe ID was not found. Please try again.");
        return;
    }

    selectedRecipeId = recipeId; 
    const ratingModal = document.getElementById("ratingModal");
    if (ratingModal) {
        ratingModal.style.display = "block"; 
    } else {
        console.error("Rating modal not found.");
    }
}


function closeRatingModal() {
    const ratingModal = document.getElementById("ratingModal");
    ratingModal.style.display = "none"; 
}

function submitRating() {
    if (!selectedRating) {
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
            stars: selectedRating,
            comment: comment,
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

document.querySelectorAll(".star-rating .star").forEach((star) => {
    star.addEventListener("click", function () {
        document.querySelectorAll(".star-rating .star").forEach((s) => s.classList.remove("selected"));

        const rating = parseInt(this.getAttribute("data-value"));
        for (let i = 1; i <= rating; i++) {
            document.querySelector(`.star-rating .star[data-value="${i}"]`).classList.add("selected");
        }

        window.selectedRating = rating;
        console.log("Seçilen Yıldız:", rating); 
    });
});

function setRating(rating) {
    selectedRating = rating;
    const stars = document.querySelectorAll('.star');
    stars.forEach((star, index) => {
        if (index < rating) {
            star.classList.add('selected');
        } else {
            star.classList.remove('selected');
        }
    });
}

</script>
<style>
.star-rating {
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 2rem;
    direction: ltr;
}

.star {
    color: #ccc;
    cursor: pointer;
    transition: color 0.3s;
}

.star:hover,
.star.selected {
    color: gold;
}

.star:hover ~ .star {
    color: #ccc;
}
</style>
