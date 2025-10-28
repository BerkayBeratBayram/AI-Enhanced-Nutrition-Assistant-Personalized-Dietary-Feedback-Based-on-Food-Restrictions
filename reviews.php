<?php
include 'auth.php'; 
include 'header.php'; 

include 'db_connection.php';

$user_id = $_SESSION['user_id'];

$sql = "SELECT r.recipe_id, r.stars, r.comment, r.created_at, r.updated_at, r.recipe_id AS recipe_name
        FROM recipe_ratings r
        WHERE r.user_id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("SQL statement preparation failed: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$reviews = [];
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}

$stmt->close(); 

$recipe_sql = "SELECT recipe_id, recipe_name, recipe_calories, recipe_fat_content, recipe_carbohydrate_content, recipe_sugar_content, recipe_protein_content, recipe_recipe_instructions, recipe_ingredients FROM recipes_new WHERE recipe_id = 6649";
$recipe_stmt = $conn->prepare($recipe_sql);
$recipe_stmt->execute();
$recipe_result = $recipe_stmt->get_result();
$recipe = $recipe_result->fetch_assoc();
$recipe_stmt->close();  

?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Reviews</h1>
                </div>
            </div>
        </div>
    </div>
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">All Reviews</h3>
                        </div>
                        <div class="card-body table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Recipe Name</th>
                                        <th>Rating</th>
                                        <th>Comment</th>
                                        <th>Actions</th> 
                                    </tr>
                                </thead>
                                <tbody>
                               
                                    <?php 

                
                                    foreach ($reviews as $review)
                                    {
                                        $recipe_sql = "SELECT * FROM recipes_new WHERE recipe_id = ?";
                                        $recipe_stmt = $conn->prepare($recipe_sql);
                                        $recipe_stmt->bind_param("i", $review['recipe_id']);  
                                        $recipe_stmt->execute();
                                        $recipe_result = $recipe_stmt->get_result();
                                        $recipe = $recipe_result->fetch_assoc();
                                        $recipe_stmt->close();  
                                     
                        
                                        echo "
                                    <tr>
                                       
                                        <td>$recipe[recipe_name]</td>
                                        <td>$review[stars]</td>
                                        <td>$review[comment]</td>
                                        <td>
                                            <form action='delete_review.php' method='POST' style='display:inline;'>
                                                <input type='hidden' name='review_id' value='$review[recipe_id]'>
                                                <button type='submit' class='btn btn-danger btn-sm'>Delete</button>
                                            </form>
                                            <button type='button' class='btn btn-info btn-sm' id='modalBTN'  onclick='openModal("; echo htmlspecialchars(json_encode($recipe)); echo")'>Details</button>
                                            
                                            </td>
                                    </tr>
                                        
                                        ";
                                    }
                                    
                                    ?>  
                              
                                                                                                        
                           
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

<script>
function openModal(recipeName) {
 
   
    const modal = document.getElementById("recipeModalReviews");
    const modalTitle = document.getElementById("modalTitle");
    const modalBody = document.getElementById("modalBody");
    const addToFavoritesButton = document.getElementById("addToFavoritesButton");

    try {
        selectedRecipeId = recipeName.recipe_id;
        modalTitle.innerText = recipeName.recipe_name;

        modalBody.innerHTML = `
  
             <strong>Calories:</strong> ${recipeName.recipe_calories} <br>
            <strong>Fat:</strong> ${recipeName.recipe_fat_content} <br>
            <strong>Carbohydrates:</strong> ${recipeName.recipe_carbohydrate_content} <br>
            <strong>Sugar:</strong> ${recipeName.recipe_sugar_content} <br>
            <strong>Protein:</strong> ${recipeName.recipe_protein_content} <br>
            <strong>Instructions:</strong> <p>${recipeName.recipe_recipe_instructions}</p>
     
        `;
        modal.style.display = "block"; 

    } catch (error) {
        console.error("Error in processing modal data:", error);
    }
}

function closeModal() {
    const modal = document.getElementById("recipeModalReviews");
    modal.style.display = "none";

}


</script>
