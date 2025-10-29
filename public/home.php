<?php
include 'auth.php';
include 'db_config.php';

try {
    $conn = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['database']);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$api_url = "http://127.0.0.1:8000/recommend/?user_id=" . urlencode($user_id) . "&limit=3";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$recipes = [];
$message = "No recommended recipes found.";

if ($response) {
    $data = json_decode($response, true);
    if (isset($data['recommended_recipes']) && !empty($data['recommended_recipes'])) {
        $recipes = $data['recommended_recipes'];
        $message = "Recommended recipes for you!";
    }
}

$update_statistics_sql = "
    UPDATE statistics
    SET 
        male_number = (SELECT COUNT(*) FROM users WHERE gender = 'male'),
        female_number = (SELECT COUNT(*) FROM users WHERE gender = 'female'),
        registered_user_number = (SELECT COUNT(*) FROM users),
        active_user_count = (SELECT COUNT(*) FROM users WHERE is_online = 1)
    WHERE id = 1;
";
$conn->query($update_statistics_sql);

$sql = "SELECT male_number, female_number, registered_user_number, active_user_count FROM statistics LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $male_number = $row['male_number'];
    $female_number = $row['female_number'];
    $registered_user_number = $row['registered_user_number'];
    $active_user_count = $row['active_user_count'];
} else {
    $male_number = 0;
    $female_number = 0;
    $registered_user_number = 0;
    $active_user_count = 0;
}

include 'header.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Homepage</h1>
                    <table style="width: 100%; table-layout: fixed;">
                    <tr>
                    <td style="width: 50%;">
                    <div style="position: relative;">
    <input type="text" id="ingredientSearch" class="form-control" placeholder="Start typing (e.g., egg, rice, tomato)" onkeyup="liveIngredientSearch()" />
    <div id="suggestions" class="suggestion-box"></div>
    <div id="selectedIngredients" class="mt-2"></div>
    <button class="btn btn-primary mt-2" onclick="applyIngredientSearch()">Apply</button>
</div>


                </div>
                </td>
                <td style="width: 50%; text-align: left; vertical-align: top;">
                                <div class="daily-tasks">
                    <h4>Daily Tasks:</h4>
                    <ul id="dailyTasks">
                         <div id="dishesRecipes"></div>
                    </ul>
                </div>            
                    </td>
                    </tr>
                    </table>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active">Dashboard v1</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo $male_number; ?></h3>
                        <p>Male User Count</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-male"></i>
                    </div>
                    <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo $female_number; ?></h3>
                        <p>Female User Count</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-female"></i>
                    </div>
                    <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo $registered_user_number; ?></h3>
                        <p>Registered User Count</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo $active_user_count; ?></h3>
                        <p>Active User Count</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>
        <div class="row mb-4">

         <div class="col-md-6">
            <h2 class="mb-4 mt-0">Popular Dishes</h2>     
                <div id="popularRecipes">
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="card">
                                <img src="https://school-rody.ru/wp-content/uploads/2023/05/df6fcead5e1660775e4de5d7779c6975.jpeg" class="card-img-top" alt="Route 1">
                                <div class="card-body">
                                    <h5 class="card-title">Your Favorites</h5>
                                    <p class="card-text">Experience the unique buttery flavor of İskender, the fulfilling taste of a hamburger, and the light sea breeze of shrimp combined.</p>
                                    <a href="DishesRecipes.php" class="btn btn-primary">Details</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="pagination" class="pagination-container">
            </div>
            </div>
<div class="col-md-6">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-12">
          <h1 class="m-0">Selected For You</h1>
        </div>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-12 d-flex align-items-stretch flex-column mb-4">
      <div class="card bg-light d-flex flex-fill">
        <div class="card-header text-muted border-bottom-0"></div>
        <div class="card-body pt-0">
          <div class="row">
            <div class="col-7">
              <ul>
                <?php foreach ($recipes as $recipe): ?>
                  <?php
  $safeName = htmlspecialchars($recipe['recipe_name'], ENT_QUOTES);
  $shortInstructions = htmlspecialchars(substr($recipe['recipe_recipe_instructions'], 0, 50)) . '...';
  $json = json_encode([
      'recipe_id' => $recipe['recipe_id'],
      'calories' => $recipe['recipe_calories'],
      'fat' => $recipe['recipe_fat_content'],
      'carbohydrate' => $recipe['recipe_carbohydrate_content'],
      'sugar' => $recipe['recipe_sugar_content'],
      'protein' => $recipe['recipe_protein_content'],
      'instructions' => $recipe['recipe_recipe_instructions']
  ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG); 
  $encodedJson = htmlspecialchars($json, ENT_QUOTES, 'UTF-8');
?>

                  <li class="mb-3">
  <strong><?= $safeName ?></strong>
  <p><?= $shortInstructions ?></p>
  <button class="btn btn-info btn-sm"
          data-name="<?= $safeName ?>"
          data-recipe='<?= $encodedJson ?>'
          onclick="showDetailsFromButton(this)">
    Details
  </button>
</li>

                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="recipeModal" class="modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="modalTitle" class="modal-title">Recipe Details</h5>
                <button type="button" class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
                <button class="btn btn-success btn-sm" onclick="addToFavorites(<?= $recipe['recipe_id']; ?>)"> Add to Favorites</button>
                <button type="button" class="btn btn-warning" onclick="openRatingModal()">
                    Rate
                </button>
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
                 <input type="hidden" id="userId" value="<?= $_SESSION['user_id']; ?>">
                 <form id="ratingForm">
        <div class="form-group">
            <label>Give a Rating (1-5):</label>
            <div id="starRating" class="star-rating">
                <span data-value="1" class="star">&#9733;</span>
                <span data-value="2" class="star">&#9733;</span>
                <span data-value="3" class="star">&#9733;</span>
                <span data-value="4" class="star">&#9733;</span>
                <span data-value="5" class="star">&#9733;</span>
            </div>
        </div>
        <div class="form-group">
            <label for="ratingComment">Leave a Comment:</label>
            <textarea id="ratingComment" class="form-control" rows="4"></textarea>
        </div>
    </form>
            </div>
            <div class="modal-footer">
            <button class="btn btn-warning" onclick="fetchOtherReviews(<?= $recipe['recipe_id']; ?>)">View Other Reviews</button>
            <div id="ratingsContainer" style="margin-top: 10px; display: none;">
                    <h5>User Reviews:</h5>
                    <ul id="ratingsList" style="list-style-type: none; padding: 0;"></ul>
                </div>
                <button type="button" class="btn btn-secondary" onclick="closeRatingModal()">Close</button>
                <button type="button" class="btn btn-success" onclick="submitRating()">Submit</button>
            </div>
        </div>
    </div>
</div>

<div id="otherReviewsModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reviews from Other Users</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <ul id="reviewsList" class="list-group">
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function openModal(recipeName, recipeDetails) {
    const modal = document.getElementById("recipeModal");
    const modalTitle = document.getElementById("modalTitle");
    const modalBody = document.getElementById("modalBody");

    selectedRecipeId = recipeDetails.recipe_id;

    modalTitle.innerText = recipeName;
    modalBody.innerHTML = Object.entries(recipeDetails)
        .map(([key, value]) => `<strong>${key}:</strong> ${value}`)
        .join("<br>");
    modal.style.display = "block";
}

        
  

function addToFavorites(recipeId) {
    const userId = document.getElementById("userId").value;

    console.log("User ID:", userId);
    console.log("Recipe ID:", recipeId);

    if (!userId || !recipeId) {
        alert("User ID or Recipe ID not found.");
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

function closeModal() {
    const modal = document.getElementById("recipeModal");
    modal.style.display = "none";
}
let currentPage = 1; 
const resultsPerPage = 3; 
let totalResults = 0; 

function searchRecipes(page = 1) {
    const searchQuery = document.getElementById("searchInput").value.trim();
    if (!searchQuery) {
        alert("Please enter an ingredient!");
        return;
    }

    const offset = (page - 1) * resultsPerPage;

    fetch(`http://127.0.0.1:8000/semantic-search/?query=${encodeURIComponent(searchQuery)}&limit=${resultsPerPage}&offset=${offset}`)
        .then(response => response.json())
        .then(data => {
            if (data.results && data.results.length > 0) {
                totalResults = data.total_results; 
                displayRecipes(data.results);
                renderPaginationButtons(page);
            } else {
                alert("No results found.");
            }
        })
        .catch(error => console.error("Error during search:", error));
}

function displayRecipes(recipes) {
    const popularRecipesContainer = document.getElementById("popularRecipes");
    popularRecipesContainer.innerHTML = "";

    recipes.forEach(recipe => {
        const recipeDetails = {
            recipe_id: recipe.recipe_id,
            calories: recipe.recipe_calories,
            fat: recipe.recipe_fat_content,
            carbohydrates: recipe.recipe_carbohydrate_content,
            sugar: recipe.recipe_sugar_content,
            protein: recipe.recipe_protein_content,
            instructions: recipe.recipe_recipe_instructions
        };

        const safeName = recipe.recipe_name.replace(/"/g, '&quot;').replace(/'/g, "&#39;");
        const safeJson = JSON.stringify(recipeDetails)
            .replace(/\\/g, "\\\\")
            .replace(/"/g, '\\"')
            .replace(/\n/g, "\\n");

        const card = document.createElement("div");
        card.className = "col-md-12 mb-4";
        card.innerHTML = `
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><strong>${safeName}</strong></h5>
                    <p class="card-text">${recipe.recipe_recipe_instructions.substring(0, 100)}...</p>
                    <button class="btn btn-info btn-sm"
                        onclick='openModal("${safeName}", JSON.parse("${safeJson}"))'>
                        Details
                    </button>
                </div>
            </div>
        `;
        popularRecipesContainer.appendChild(card);
    });
}


function renderPaginationButtons(currentPage) {
    const paginationContainer = document.getElementById("pagination");
    paginationContainer.innerHTML = ""; 

    const totalPages = Math.min(17, Math.ceil(totalResults / resultsPerPage)); 

    for (let i = 1; i <= totalPages; i++) {
        const button = document.createElement("button");
        button.innerText = i;
        button.className = `btn ${i === currentPage ? "btn-primary" : "btn-secondary"} m-1`;
        button.onclick = () => searchRecipes(i);
        paginationContainer.appendChild(button);
    }
}

function openRatingModal() {
    const ratingModal = document.getElementById("ratingModal");
    ratingModal.style.display = "block"; 
}

function closeRatingModal() {
    const ratingModal = document.getElementById("ratingModal");
    ratingModal.style.display = "none"; 
}

function submitRating() {
    if (!selectedRecipeId) {
        alert("Please select a recipe first.");
        return;
    }

    
    const selectedStar = document.querySelectorAll(".star-rating .star.selected");
    if (!selectedStar || selectedStar.length === 0) {
        alert("Please select a rating.");
        return;
    }

    const stars = selectedStar.length; 
    const comment = document.getElementById("ratingComment").value.trim();

    if (!comment) {
        alert("Please enter a comment.");
        return;
    }

    
    console.log("Data being sent:", {
        recipe_id: selectedRecipeId,
        user_id: userId.value,
        stars: stars,
        comment: comment,
    });

    
    fetch('http://127.0.0.1:8000/rate_recipe/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            recipe_id: selectedRecipeId,
            user_id: userId.value,
            stars: stars,
            comment: comment,
        }),
    })
    .then((response) => {
        if (!response.ok) {
            throw new Error("An error occurred during the rating.");
        }
        return response.json();
    })
    .then((data) => {
        alert(data.message || "Rating submitted successfully!");
    })
    .catch((error) => console.error("Error submitting rating:", error));

    
    closeRatingModal();
    document.getElementById("ratingForm").reset();
}

document.querySelectorAll(".star-rating .star").forEach((star) => {
    star.addEventListener("click", function () {
        
        document.querySelectorAll(".star-rating .star").forEach((s) => s.classList.remove("selected"));

        
        const rating = parseInt(this.getAttribute("data-value"));
        for (let i = 1; i <= rating; i++) {
            document.querySelector(`.star-rating .star[data-value="${i}"]`).classList.add("selected");
        }

        console.log("Selected Star:", rating); 
    });
});

function showRatings() {
    const userId = document.getElementById("userId").value; 
    fetch(`http://127.0.0.1:8000/user_ratings/?user_id=${encodeURIComponent(userId)}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById("userRatings");
            container.innerHTML = ""; 
            if (data.ratings) {
                data.ratings.forEach(rating => {
                    const ratingDiv = `
                        <div class="rating">
                            <h4>${rating.recipe_name}</h4>
                            <p>Rating: ${rating.stars}</p>
                            <p>Comment: ${rating.comment}</p>
                        </div>
                    `;
                    container.innerHTML += ratingDiv;
                });
            } else {
                container.innerHTML = `<p>${data.message}</p>`;
            }

            document.getElementById("ratingsContainer").style.display = "block";
        })
        .catch(error => console.error("Error loading ratings:", error));
}

function showOtherRatings() {
    if (!selectedRecipeId) {
        alert("Please select a recipe first.");
        return;
    }
    fetch(`http://127.0.0.1:8000/user_ratings/?recipe_id=${encodeURIComponent(selectedRecipeId)}`)
        .then(response => response.json())
        .then(data => {
            const ratingsContainer = document.getElementById("otherRatings");
            const ratingsList = document.getElementById("ratingsList");

            ratingsList.innerHTML = ""; 

            if (data.ratings && data.ratings.length > 0) {
                data.ratings.forEach(rating => {
                    const listItem = document.createElement("li");
                    listItem.innerHTML = `
                        <strong>${rating.user_name}:</strong>
                        <span>Rating: ${rating.stars} - Comment: ${rating.comment}</span>
                    `;
                    ratingsList.appendChild(listItem);
                });
                ratingsContainer.style.display = "block";
            } else {
                ratingsContainer.style.display = "block";
                ratingsList.innerHTML = "<li>No reviews available for this recipe.</li>";
            }
        })
        .catch(error => {
            console.error("Error loading review data:", error);
            alert("An error occurred while loading reviews.");
        });
}
function fetchRecipeRatings(recipeId) {
    if (!selectedRecipeId) {
        alert("Please select a recipe first.");
        return;
    }

    fetch(`http://127.0.0.1:8000/ratings/?recipe_id=${encodeURIComponent(recipeId)}`)
        .then(response => response.json())
        .then(data => {
            const ratingsContainer = document.getElementById("ratingsContainer");
            ratingsContainer.innerHTML = ""; 

            if (data.ratings && data.ratings.length > 0) {
                data.ratings.forEach(rating => {
                    const ratingElement = `
                        <div class="rating">
                            <p><strong>Rating:</strong> ${rating.stars}</p>
                            <p><strong>Comment:</strong> ${rating.comment}</p>
                        </div>
                    `;
                    ratingsContainer.innerHTML += ratingElement;
                });
            } else {
                ratingsContainer.innerHTML = "<p>No reviews yet.</p>";
            }

            ratingsContainer.style.display = "block";
        })
        .catch(error => console.error("Error loading reviews:", error));
}

function displayRatings(ratings) {
    const ratingsContainer = document.getElementById("ratingsContainer");
    ratingsContainer.innerHTML = ""; 

    ratings.forEach(rating => {
        const ratingDiv = document.createElement("div");
        ratingDiv.innerHTML = `
            <strong>${rating.user_name}</strong> (${rating.stars} stars): 
            <p>${rating.comment}</p>
        `;
        ratingsContainer.appendChild(ratingDiv);
    });
}
function fetchOtherReviews(recipeId) {
    if (!recipeId) {
        alert("Invalid recipe ID.");
        return;
    }

    fetch(`/get_reviews.php?recipe_id=${recipeId}`)
        .then(response => response.json())
        .then(data => {
            const reviewsList = document.getElementById('reviewsList');
            reviewsList.innerHTML = ''; 

            if (data.reviews && data.reviews.length > 0) {
                data.reviews.forEach(review => {
                    const listItem = document.createElement('li');
                    listItem.className = 'list-group-item';
                    listItem.innerHTML = `
                        <strong>User:</strong> ${review.user_name || 'Anonymous'} <br>
                        <strong>Rating:</strong> ${review.stars} <br>
                        <strong>Comment:</strong> ${review.comment || 'No comment'}
                    `;
                    reviewsList.appendChild(listItem);
                });
            } else {
                reviewsList.innerHTML = '<li class="list-group-item">No reviews yet.</li>';
            }

            
            $('#otherReviewsModal').modal('show');
        })
        .catch(error => {
            console.error('Error fetching reviews:', error);
            alert('An error occurred while fetching reviews.');
        });
}

</script>
<script src="app.js" defer></script>
<style>
    .search-bar {
        margin: 10px 0;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .search-bar input {
        width: 300px;
        padding: 10px;
        margin-right: 5px;
        border: 1px solid #ddd;
        border-radius: 5px;
    }
    .search-bar button {
        padding: 10px 15px;
        border: none;
        background-color: #007bff;
        color: white;
        border-radius: 5px;
        cursor: pointer;
    }
    .search-bar button:hover {
        background-color: #0056b3;
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
    overflow: hidden;
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
.card-img-top {
    max-height: 227px; 
    width: 100%; 
    object-fit: cover; 
    border-radius: 5px; 
}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
<style>
#chat-button {
  position: fixed;
  bottom: 20px;
  right: 20px;
  background-color: #4CAF50;
  border: none;
  color: white;
  border-radius: 50%;
  padding: 16px;
  cursor: pointer;
  z-index: 1000;
}

#chat-window {
  display: none;
  position: fixed;
  bottom: 80px;
  right: 20px;
  width: 500px;
  height: 600px;
font-size: 16px;
  background: white;
  border: 1px solid #ccc;
  border-radius: 10px;
  z-index: 1000;
  box-shadow: 0 0 10px rgba(0,0,0,0.3);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

#chat-messages {
    padding-bottom: 10px; 
  flex: 1;
  padding: 10px;
  overflow-y: auto;
  font-size: 14px;
}

#chat-input {
  display: flex;
  border-top: 1px solid #ccc;
}

#chat-input input {
  flex: 1;
  border: none;
  padding: 10px;
}

#chat-input button {
  border: none;
  background: #4CAF50;
  color: white;
  padding: 10px;
}

.recipe-btn {
  background-color: #28a745;
  color: white;
  padding: 5px 10px;
  border: none;
  border-radius: 4px;
  margin-top: 5px;
  cursor: pointer;
  font-size: 13px;
}
.recipe-btn:hover {
  background-color: #218838;
}
.suggestion-box {
    border: 1px solid #ccc;
    background: white;
    max-height: 150px;
    overflow-y: auto;
    position: absolute;
    z-index: 1000;
    width: 100%;
}
.suggestion-box div {
    padding: 8px;
    cursor: pointer;
}
.suggestion-box div:hover {
    background-color: #f1f1f1;
}
.tag {
    background-color: #28a745;
    color: white;
    padding: 5px 10px;
    margin: 3px;
    display: inline-block;
    border-radius: 15px;
    font-size: 14px;
}
.remove-tag {
    margin-left: 8px;
    cursor: pointer;
    color: white;
    font-weight: bold;
}
.bot-msg, .user-msg {
  max-width: 80%;
  padding: 10px 14px;
  margin: 8px;
  border-radius: 12px;
  line-height: 1.4;
  word-wrap: break-word;
  font-size: 14px;
  white-space: pre-wrap;
}

.bot-msg {
  background: #f8f9fa;
  align-self: flex-start;
  border-radius: 16px;
  padding: 16px 20px;
  margin: 6px 0;
  line-height: 1.6;
  font-size: 16px;
  font-family: 'Segoe UI', sans-serif;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
  max-width: 600px;
  white-space: pre-wrap;
  overflow-wrap: break-word;

}




.user-msg {
  background: #d4edda;
  align-self: flex-end;
  border-top-right-radius: 0;
}

.recipe-box {
  background: #fff3cd;
  border-left: 4px solid #ffc107;
  padding: 12px;
  margin: 8px;
  border-radius: 8px;
  white-space: pre-wrap;
  font-size: 14px;
}
#refresh-button-area button:hover {
  background-color: #e0e0e0;
}

</style>

<div id="chat-window">
   <div style="position: relative;">
  <button onclick="closeChat()" style="
    position: absolute;
    top: 8px;
    right: 10px;
    background: none;
    border: none;
    font-size: 14px;
    color: #666;
    cursor: pointer;
    padding: 0;
    line-height: 1;
  " aria-label="Close chat">×</button>
</div>


    <div id="refresh-button-area" style="display:none; text-align:center; padding:10px;">
  <button onclick="resetChat()" style="
      padding: 8px 16px;
      background: #f5f5f5;
      color: #333;
      border: 1px solid #ccc;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      transition: background 0.2s ease;">
    🔁 Reset Chat
  </button>
</div>



  <div id="chat-messages"></div>
<div id="bot-recipe-details" style="display:none; padding:10px; border-top:1px solid #ccc; max-height: 200px; overflow-y: auto;"></div>
  <div id="chat-input">
    <input type="text" id="user-message" placeholder="Write your question..." />
    <button onclick="sendMessage()">➤</button>
  </div>
</div>

<button id="chat-button"><i class="fas fa-comments"></i></button>

<div id="bot-selector" style="display:none; position:fixed; bottom:80px; right:20px; background:white; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,0.25); z-index:1000; overflow:hidden; min-width:200px; font-family:Arial, sans-serif;">
  <div style="background:#4CAF50; color:white; font-weight:bold; text-align:center; padding:10px;">Select Assistant Mode</div>
  <button onclick="launchBot('semantic')" style="padding:10px; border:none; background:white; color:#333; width:100%; text-align:left; border-top:1px solid #eee; font-size:14px;"> Smart Recipe Bot</button>
  <button onclick="launchBot('generator')" style="padding:10px; border:none; background:white; color:#333; width:100%; text-align:left; border-top:1px solid #eee; font-size:14px;"> Recipe Generator</button>
</div>

<script>
const user_id = <?= json_encode($_SESSION['user_id']) ?>;

document.getElementById("chat-button").onclick = () => {
  const selector = document.getElementById("bot-selector");
  selector.style.display = selector.style.display === "none" ? "block" : "none";
};

function launchBot(mode) {
  const chat = document.getElementById("chat-window");
  chat.style.display = "flex";
  document.getElementById("bot-selector").style.display = "none";
  window.chatMode = mode;
  document.getElementById("chat-messages").innerHTML = ""; 
  document.getElementById("bot-recipe-details").style.display = "none";

  if (mode === "generator") {
    chatState = "awaiting_recipe_name";
    recipeDraft = {};
    document.getElementById("chat-messages").innerHTML += `
<div class="bot-msg"><strong>Bot:</strong> Welcome to the Recipe Generator! What dish would you like to make?</div>
    `;
  }

  if (mode === "semantic") {
    chatState = null;
    document.getElementById("chat-messages").innerHTML += `
<div class="bot-msg"><strong>Bot:</strong> Hello! I’m your smart recipe assistant. Ask me what you want to cook or eat today!</div>
    `;
  }
}




let latestRecipe = null;
let chatState = null;   
let recipeDraft = {};   

async function sendMessage() {
  const msg = document.getElementById("user-message").value.trim();
  if (!msg) return;

  const chatBox = document.getElementById("chat-messages");
  chatBox.innerHTML += `<div class="user-msg"><strong>You:</strong> ${msg}</div>`;
  document.getElementById("user-message").value = "";

  if (window.chatMode === "generator") {
    if (!chatState) {
      chatBox.innerHTML += `<div class="bot-msg"><strong>Bot:</strong> Welcome to the Recipe Generator! 🍳 What dish would you like to make?</div>`;
      chatState = "awaiting_recipe_name";
      return;
    } else if (chatState === "awaiting_recipe_name") {
      recipeDraft.name = msg;
      chatBox.innerHTML += `<div class="bot-msg"><strong>Bot:</strong> Awesome! Please enter the ingredients using commas (like: milk, butter, sugar)</div>`;
      chatState = "awaiting_ingredients";
      return;
    } else if (chatState === "awaiting_ingredients") {
      recipeDraft.ingredients = msg;
      chatBox.innerHTML += `<div><b>Bot:</b> Creating recipe, please wait...</div>`;

      try {
        const response = await fetch("http://localhost:8000/generate-recipe", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(recipeDraft)
        });
        const data = await response.json();

        chatBox.innerHTML += `
<div class="bot-msg"><strong>${recipeDraft.name}</strong> recipe generated:</div>
<div class="recipe-box">${data.generated_recipe}</div>
`;
        document.getElementById("refresh-button-area").style.display = "block";
      } catch (err) {
        chatBox.innerHTML += `<div><b>Bot:</b> Error while generating the recipe 😓</div>`;
      }

      chatState = null;
      recipeDraft = {};
      return;
    }

    return;
  }

  
  try {
    const response = await fetch("http://localhost:8000/rag_recommendation?" +
      new URLSearchParams({
        user_query: msg,
        user_id: user_id
      }));

    const data = await response.json();
    const botText = data.recommendation || "No suitable recipe found.";

    chatBox.innerHTML += `
      <div class="bot-msg"><strong>Bot:</strong> ${botText}</div>
    `;
    chatBox.scrollTop = chatBox.scrollHeight;
  } catch (error) {
    chatBox.innerHTML += `<div class="bot-msg"><strong>Bot:</strong> Error getting response.</div>`;
    console.error(error);
  }
}



function showBotRecipeDetails() {
  const detailBox = document.getElementById("bot-recipe-details");
  if (!latestRecipe) {
    detailBox.innerHTML = `<p>No recipe details available.</p>`;
  } else {
    detailBox.innerHTML = `
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <h4 style="margin: 0;">${latestRecipe.name}</h4>
        <button onclick="hideBotRecipeDetails()" style="background:none;border:none;font-size:16px;cursor:pointer;">✖</button>
      </div>
      <p><strong>Ingredients:</strong> ${latestRecipe.ingredients}</p>
      <p><strong>Instructions:</strong> ${latestRecipe.instructions}</p>
    `;
  }
  detailBox.style.display = "block";
}

function hideBotRecipeDetails() {
  document.getElementById("bot-recipe-details").style.display = "none";
}

</script>
<script>
let selectedIngredients = [];

function liveIngredientSearch() {
    const query = document.getElementById("ingredientSearch").value.trim();
    if (!query) {
        document.getElementById("suggestions").innerHTML = "";
        return;
    }

    fetch(`ingredients.php?term=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
            const suggestionBox = document.getElementById("suggestions");
            suggestionBox.innerHTML = "";
            data.forEach(ingredient => {
                if (!selectedIngredients.includes(ingredient)) {
                    const div = document.createElement("div");
                    div.textContent = ingredient;
                    div.onclick = () => selectIngredient(ingredient);
                    suggestionBox.appendChild(div);
                }
            });
        });
}

function selectIngredient(ingredient) {
    selectedIngredients.push(ingredient);
    document.getElementById("ingredientSearch").value = "";
    document.getElementById("suggestions").innerHTML = "";
    updateSelectedTags();
}

function updateSelectedTags() {
    const container = document.getElementById("selectedIngredients");
    container.innerHTML = "";

    selectedIngredients.forEach((ing, index) => {
        const tag = document.createElement("span");
        tag.className = "tag";
        tag.innerHTML = `${ing} <span class="remove-tag" onclick="removeIngredient(${index})">&times;</span>`;
        container.appendChild(tag);
    });
}
function removeIngredient(index) {
    selectedIngredients.splice(index, 1); 
    updateSelectedTags(); 
}


currentPage = 1;
const recipesPerPage = 3;
let globalSearchResults = [];

function applyIngredientSearch() {
    if (selectedIngredients.length === 0) {
        alert("Please select at least one ingredient.");
        return;
    }

    const ingredients = selectedIngredients.join(",");
    fetch(`http://127.0.0.1:8000/search_by_ingredients/?ingredients=${encodeURIComponent(ingredients)}`)
        .then(res => res.json())
        .then(data => {
            globalSearchResults = data.results || [];
            currentPage = 1;
            renderSearchPage();  
        })
        .catch(err => console.error("Search error:", err));
}


function renderSearchPage() {
    const container = document.getElementById("popularRecipes");
    container.innerHTML = "";

    const start = (currentPage - 1) * recipesPerPage;
    const end = start + recipesPerPage;
    const paginated = globalSearchResults.slice(start, end);

    if (paginated.length === 0) {
        container.innerHTML = "<p>No recipes found.</p>";
        return;
    }

    const row = document.createElement("div");
    row.className = "row";

    paginated.forEach(recipe => {
        const recipeDetails = {
            recipe_id: recipe.recipe_id,
            calories: recipe.recipe_calories,
            fat: recipe.recipe_fat_content,
            carbohydrates: recipe.recipe_carbohydrate_content,
            sugar: recipe.recipe_sugar_content,
            protein: recipe.recipe_protein_content,
            instructions: recipe.recipe_recipe_instructions
        };

        const recipeJson = JSON.stringify(recipeDetails).replace(/\\/g, "\\\\").replace(/"/g, '\\"');


const col = document.createElement("div");
col.className = "col-md-12 mb-3";
col.innerHTML = `
    <div class="card">
        <div class="card-body">
            <h5 class="card-title"><strong>${recipe.recipe_name}</strong></h5>
            <p class="card-text">${recipe.recipe_recipe_instructions.substring(0, 100)}...</p>
            <button class="btn btn-info btn-sm" onclick='openModal("${recipe.recipe_name}", JSON.parse("${recipeJson}"))'>Details</button>
        </div>
    </div>
`;

        row.appendChild(col);
    });

    container.appendChild(row);
    renderPagination(container);
}



function renderPagination(container) {
    const totalPages = Math.ceil(globalSearchResults.length / recipesPerPage); 
    if (totalPages <= 1) return;

    const pagination = document.createElement("div");
    pagination.className = "mt-3";

    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement("button");
        btn.className = `btn btn-${i === currentPage ? "primary" : "secondary"} m-1`;
        btn.textContent = i;
        btn.onclick = () => {
            currentPage = i;
            renderSearchPage();
        };
        pagination.appendChild(btn);
    }

    container.appendChild(pagination);
}
function resetChat() {
  document.getElementById("chat-messages").innerHTML = "";
  document.getElementById("bot-recipe-details").style.display = "none";
  document.getElementById("refresh-button-area").style.display = "none";
  chatState = null;
  recipeDraft = {};
  latestRecipe = null;

  if (window.chatMode === "generator") {
    chatState = "awaiting_recipe_name";
    document.getElementById("chat-messages").innerHTML += `
<div class="bot-msg"><strong>Bot:</strong> Welcome to the Recipe Generator!  What dish would you like to make?</div>

    `;
  }
}
function closeChat() {
  document.getElementById("chat-window").style.display = "none";
}
function showDetailsFromButton(btn) {
  const name = btn.getAttribute('data-name');
  const recipeData = btn.getAttribute('data-recipe');

  try {
    const parsed = JSON.parse(recipeData);
    openModal(name, parsed);
  } catch (e) {
    console.error("Tarif hatalı JSON verisi içeriyor:", e);
    alert("Oops! This recipe can't be opened due to formatting issues.");
  }
}


</script>
