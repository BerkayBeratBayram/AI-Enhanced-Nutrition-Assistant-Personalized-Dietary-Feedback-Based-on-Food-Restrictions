document.addEventListener("DOMContentLoaded", () => {
    const dailyTasks = document.getElementById("dailyTasks");

    if (dailyTasks) {
        fetch("http://127.0.0.1:8000/daily_tasks/")
            .then((response) => response.json())
            .then((data) => {
                dailyTasks.innerHTML = ""; 
                if (data.tasks && data.tasks.length > 0) {
                    data.tasks.forEach((task) => {
                        const listItem = document.createElement("li");
                        listItem.textContent = task.task_description;
                        listItem.style.padding = "10px 5px";
                        listItem.style.borderBottom = "1px solid #ddd";
                        dailyTasks.appendChild(listItem);
                    });
                } else {
                    dailyTasks.innerHTML = "<li>Görev bulunamadı.</li>";
                }
            })
            .catch((error) => {
                console.error("API çağrısı sırasında hata oluştu:", error);
            });
    } else {
        console.error("dailyTasks elementi bulunamadı!");
    }
});
document.addEventListener("DOMContentLoaded", () => {
    const dishesRecipes = document.getElementById("dishesRecipes");
    const userId = document.getElementById("user_id").value; 

    if (dishesRecipes) {
        fetch(`http://127.0.0.1:8000/recommend/?user_id=${userId}`)
            .then((response) => {
                if (!response.ok) {
                    throw new Error("API isteği başarısız oldu!");
                }
                return response.json();
            })
            .then((data) => {
                dishesRecipes.innerHTML = ""; 
                if (data.recommended_recipes && data.recommended_recipes.length > 0) {
                    data.recommended_recipes.forEach((recipe) => {
                        const ingredients = recipe.recipe_ingredients.replace(/[{}']/g, ""); 
                        const listItem = document.createElement("li");
                        listItem.innerHTML = `
                            <strong>${recipe.recipe_name}</strong>
                            Malzemeler: ${ingredients}<br>
                            <button onclick="showDetails(${recipe.recipe_id})">Detaylar</button>
                        `;
                        dishesRecipes.appendChild(listItem);
                    });
                } else {
                    dishesRecipes.innerHTML = "<li>Uygun tarif bulunamadı.</li>";
                }
            })
            .catch((error) => {
                console.error("API çağrısı sırasında hata oluştu:", error);
            });
    } else {
        console.error("dishesRecipes elementi bulunamadı!");
    }
});

function showDetails(recipeId) {
    alert(`Tarif detayları için ID: ${recipeId}`);
   
}
