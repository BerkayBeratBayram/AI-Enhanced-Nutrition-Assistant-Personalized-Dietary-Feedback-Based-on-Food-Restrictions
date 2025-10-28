from nltk.stem import WordNetLemmatizer


from fastapi import FastAPI, HTTPException, Query
from fastapi.middleware.cors import CORSMiddleware
import mysql.connector
from mysql.connector import connect
from mysql.connector import pooling
from owlready2 import get_ontology
import random
from sklearn.metrics.pairwise import cosine_similarity
from rapidfuzz import fuzz
from random import sample
import pandas as pd
import numpy as np
from sentence_transformers import SentenceTransformer
from pydantic import BaseModel
from transformers import T5Tokenizer, T5ForConditionalGeneration
import torch
from retriever import query_recipes_rag

from vector_store import get_vector_store


from langchain_community.vectorstores import FAISS
from langchain_huggingface import HuggingFaceEmbeddings
from langchain.schema import Document
from dotenv import load_dotenv
from langchain_community.llms import OpenAI
from sklearn.metrics.pairwise import cosine_similarity
from typing import List
import os


lemmatizer = WordNetLemmatizer()

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://127.0.0.2:8000"],  
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)



db_config = {
    "host": "localhost",
    "user": "root",
    "password": "2634",
    "database": "dietapp",
    "port": 3306,
}



connection_pool = pooling.MySQLConnectionPool(pool_name="mypool", pool_size=10, **db_config)
foodon_path = "FoodOn.owl"
try:
    foodon = get_ontology(foodon_path).load()
    print("Ontology loaded.")
    ontology_terms = set(
        cls.label[0].lower()
        for cls in foodon.classes()
        if hasattr(cls, 'label') and cls.label
    )
except Exception as e:
    print(f"Ontology could not be loaded: {e}")
    foodon = None
    ontology_terms = set()


cached_restricted_ingredients = {}


def get_restricted_ingredients_cached(disease):
    if disease not in cached_restricted_ingredients:
        try:
            conn = connection_pool.get_connection()
            cursor = conn.cursor(dictionary=True)
            query = """
            SELECT banned_product 
            FROM bannedproducts 
            JOIN diseases ON diseases.id = bannedproducts.disease_id 
            WHERE diseases.disease_name = %s;
            """
            cursor.execute(query, (disease,))
            results = cursor.fetchall()
            cursor.close()
            conn.close()

            
            restricted_items = [lemmatizer.lemmatize(row["banned_product"].lower()) for row in results]
            cached_restricted_ingredients[disease] = restricted_items
        except mysql.connector.Error as e:
            print(f"MySQL error: {e}")
            raise HTTPException(status_code=500, detail="Database error occurred.")
    return cached_restricted_ingredients[disease]


def match_with_ontology(ingredient):
    """
    Matches quickly using cached ontology terms.
    """
    return ingredient.lower() in ontology_terms


def get_user_disease(user_id):
    try:
        conn = connection_pool.get_connection()
        cursor = conn.cursor(dictionary=True)
        query = "SELECT disease FROM users WHERE user_id = %s;"
        cursor.execute(query, (user_id,))
        result = cursor.fetchone()
        cursor.close()
        conn.close()

        if not result or not result["disease"]:
            raise HTTPException(status_code=404, detail="User's disease not found.")
        return result["disease"].lower()
    except mysql.connector.Error as e:
        print(f"MySQL error: {e}")
        raise HTTPException(status_code=500, detail="Database error occurred.")

def parse_ingredients(ingredients_str):
    """
    Converts the ingredient list from string format to a list.
    """
    try:
        return [lemmatizer.lemmatize(ingredient.lower().strip()) for ingredient in ingredients_str.split(",")]
    except (ValueError, SyntaxError):
        return []

def is_restricted_ingredient(ingredient, restricted_items, threshold=85):
    """
    Checks if the ingredient matches any of the restricted products.
    """
    ingredient_root = lemmatizer.lemmatize(ingredient.lower())  
    for restricted_item in restricted_items:
        restricted_root = lemmatizer.lemmatize(restricted_item.lower())  
        if (
            ingredient_root in restricted_root or
            restricted_root in ingredient_root or
            fuzz.WRatio(ingredient_root, restricted_root) >= threshold
        ):
            return True
    return False


def recommend_recipes(disease, limit=10):
    try:
        restricted_items = get_restricted_ingredients_cached(disease)
        safe_recipes = []

        conn = connection_pool.get_connection()
        cursor = conn.cursor(dictionary=True)

        query = """
        SELECT recipe_id, recipe_name, recipe_ingredients, recipe_calories, 
               recipe_fat_content, recipe_sugar_content, recipe_protein_content, 
               recipe_carbohydrate_content, recipe_recipe_instructions
        FROM recipes
        """
        cursor.execute(query)
        all_recipes = cursor.fetchall()
        cursor.close()
        conn.close()

        for row in all_recipes:
            ingredients = parse_ingredients(row["recipe_ingredients"])
            is_safe = True

            for ingredient in ingredients:
                if (
                    is_restricted_ingredient(ingredient, restricted_items) or
                    match_with_ontology(ingredient)
                ):
                    is_safe = False
                    break

            if is_safe:
                safe_recipes.append({
                    "recipe_id": row["recipe_id"],
                    "recipe_name": row["recipe_name"],
                    "recipe_ingredients": row["recipe_ingredients"],
                    "recipe_calories": row["recipe_calories"],
                    "recipe_fat_content": row["recipe_fat_content"],
                    "recipe_sugar_content": row["recipe_sugar_content"],
                    "recipe_protein_content": row["recipe_protein_content"],
                    "recipe_carbohydrate_content": row["recipe_carbohydrate_content"],
                    "recipe_recipe_instructions": row["recipe_recipe_instructions"],
                })

        if not safe_recipes:
            return []

        return random.sample(safe_recipes, min(limit, len(safe_recipes)))

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Recipe recommendation error: {e}")
@app.get("/")
def home():
    return {"message": "Welcome to the Recipe Recommendation API!"}



print("FAISS indeksi yükleniyor...")
db = get_vector_store()
print("Hazır!")


@app.get("/recommend/")
def recommend(
    user_id: int = Query(..., description="User ID is required"),
    limit: int = Query(10, description="Number of recipes to return")
):
    try:
        
        disease = get_user_disease(user_id)
        recipes = recommend_recipes(disease, limit=limit)

        if not recipes:
            return {
                "message": "No suitable recipes found.",
                "recommended_recipes": []
            }

        return {
            "message": f"Recommended recipes for {disease}:",
            "recommended_recipes": recipes
        }

    except Exception as e:
        print(f"Error in /recommend/: {str(e)}")
        raise HTTPException(status_code=500, detail=f"An error occurred: {e}")

@app.post("/favorites/")
def add_to_favorites(user_id: int = Query(...), recipe_id: int = Query(...)):
    try:
        conn = connection_pool.get_connection()
        cursor = conn.cursor()

 
        query = "INSERT IGNORE INTO favorites (user_id, recipe_id) VALUES (%s, %s)"
        cursor.execute(query, (user_id, recipe_id))
        conn.commit()

        if cursor.rowcount > 0:
            message = "Recipe added to favorites successfully."
        else:
            message = "Recipe is already in favorites."

        cursor.close()
        conn.close()
        return {"message": message}

    except mysql.connector.Error as e:
        print(f"MySQL error: {str(e)}")
        raise HTTPException(status_code=500, detail="Database error occurred.")
    except Exception as e:
        print(f"Error in /favorites/: {str(e)}")
        raise HTTPException(status_code=500, detail="An error occurred.")

@app.get("/favorites/")
def get_favorites(user_id: int = Query(...)):
    try:
        conn = connection_pool.get_connection()
        cursor = conn.cursor(dictionary=True)

        
        query = """
        SELECT r.recipe_id, r.recipe_name, r.recipe_calories, r.recipe_fat_content,
               r.recipe_sugar_content, r.recipe_protein_content, r.recipe_carbohydrate_content,
               r.recipe_recipe_instructions
        FROM favorites f
        JOIN recipes r ON f.recipe_id = r.recipe_id
        WHERE f.user_id = %s
        """
        cursor.execute(query, (user_id,))
        favorite_recipes = cursor.fetchall()

        cursor.close()
        conn.close()

        if not favorite_recipes:
            return {"message": "No favorite recipes found.", "favorites": []}

        return {"message": "Favorite recipes retrieved successfully.", "favorites": favorite_recipes}

    except mysql.connector.Error as e:
        print(f"MySQL error: {str(e)}")
        raise HTTPException(status_code=500, detail="Database error occurred.")
    except Exception as e:
        print(f"Error in /favorites/: {str(e)}")
        raise HTTPException(status_code=500, detail="An error occurred.")

@app.delete("/favorites/")
def remove_from_favorites(user_id: int = Query(...), recipe_id: int = Query(...)):
    try:
        conn = connection_pool.get_connection()
        cursor = conn.cursor()


        query = "DELETE FROM favorites WHERE user_id = %s AND recipe_id = %s"
        cursor.execute(query, (user_id, recipe_id))
        conn.commit()

        if cursor.rowcount > 0:
            message = "Recipe removed from favorites successfully."
        else:
            message = "Recipe not found in favorites."

        cursor.close()
        conn.close()
        return {"message": message}

    except mysql.connector.Error as e:
        print(f"MySQL error: {str(e)}")
        raise HTTPException(status_code=500, detail="Database error occurred.")
    except Exception as e:
        print(f"Error in /favorites/: {str(e)}")
        raise HTTPException(status_code=500, detail="An error occurred.")

@app.get("/search/")
def search_recipes(
    query: str = Query(..., description="The recipe name or ingredient you want to search for"),
    limit: int = Query(10, description="The number of results to return per page"),
    offset: int = Query(0, description="The starting point of the results")
):
    """
    Searches for recipes by name or ingredients and supports pagination.
    Includes name, calories, fat, recipe, protein, and carbohydrate information for each result.
    """
    try:
        conn = connection_pool.get_connection()
        cursor = conn.cursor(dictionary=True)

        search_query = """
        SELECT recipe_id, recipe_name, recipe_calories, recipe_fat_content, 
               recipe_carbohydrate_content, recipe_sugar_content, recipe_protein_content, 
               recipe_recipe_instructions, recipe_ingredients
        FROM recipes
        WHERE recipe_name LIKE %s OR recipe_ingredients LIKE %s
        LIMIT %s OFFSET %s;
        """
        like_query = f"%{query.lower()}%"  
        cursor.execute(search_query, (like_query, like_query, limit, offset))

        results = cursor.fetchall()
        cursor.close()
        conn.close()

        if not results:
            return {
                "message": "No recipes found matching the search criteria.",
                "results": []
            }

        total_query = """
        SELECT COUNT(*) as total
        FROM recipes
        WHERE recipe_name LIKE %s OR recipe_ingredients LIKE %s;
        """
        conn = connection_pool.get_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute(total_query, (like_query, like_query))
        total_result = cursor.fetchone()
        cursor.close()
        conn.close()

        return {
            "message": f"Search results for {query}:",
            "total_results": total_result["total"],
            "results": [
                {
                    "recipe_id": row["recipe_id"],
                    "recipe_name": row["recipe_name"],
                    "recipe_calories": row["recipe_calories"],
                    "recipe_fat_content": row["recipe_fat_content"],
                    "recipe_carbohydrate_content": row["recipe_carbohydrate_content"],
                    "recipe_sugar_content": row["recipe_sugar_content"],
                    "recipe_protein_content": row["recipe_protein_content"],
                    "recipe_recipe_instructions": row["recipe_recipe_instructions"],
                    "recipe_ingredients": row["recipe_ingredients"],
                }
                for row in results
            ]
        }
    except mysql.connector.Error as e:
        print(f"MySQL error: {str(e)}")
        raise HTTPException(status_code=500, detail="Database error occurred.")
    except Exception as e:
        print(f"Error in /search/: {str(e)}")
        raise HTTPException(status_code=500, detail="An error occurred.")

from pydantic import BaseModel

class RecipeRating(BaseModel):
    recipe_id: int
    user_id: int
    stars: int
    comment: str

@app.post("/rate_recipe/")
def rate_recipe(rating: RecipeRating):
    print(rating)  
    try:
        conn = connection_pool.get_connection()
        cursor = conn.cursor()
        query = """
        INSERT INTO recipe_ratings (recipe_id, user_id, stars, comment)
        VALUES (%s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            stars = VALUES(stars),
            comment = VALUES(comment)
        """
        cursor.execute(query, (rating.recipe_id, rating.user_id, rating.stars, rating.comment))
        conn.commit()
        return {"message": "Rating successfully saved."}
    except mysql.connector.Error as e:
        raise HTTPException(status_code=500, detail="Database error occurred.")
    finally:
        cursor.close()
        conn.close()

@app.get("/user_ratings/")
def get_user_ratings(user_id: int = Query(..., description="The user's ID")):
    """
    Endpoint that returns the user's comments and ratings.
    """
    try:
        conn = connection_pool.get_connection()
        cursor = conn.cursor(dictionary=True)

        query = """
        SELECT rr.recipe_id, r.recipe_name, rr.stars, rr.comment
        FROM recipe_ratings rr
        JOIN recipes_nw r ON rr.recipe_id = r.id
        WHERE rr.user_id = %s
        """
        cursor.execute(query, (user_id,))
        results = cursor.fetchall()

        cursor.close()
        conn.close()

        if not results:
            return {"message": "You have not rated any recipes yet."}

        return {"ratings": results}
    except mysql.connector.Error as e:
        print(f"MySQL error: {str(e)}")
        raise HTTPException(status_code=500, detail="Database error occurred.")
    except Exception as e:
        print(f"Error in /user_ratings/: {str(e)}")
        raise HTTPException(status_code=500, detail="An error occurred.")

@app.get("/daily_tasks/")
def get_daily_tasks():
    """
    Endpoint that returns randomly selected daily tasks.
    """
    try:
        conn = connection_pool.get_connection()
        cursor = conn.cursor(dictionary=True)

        query = "SELECT id, task_description, is_completed FROM daily_tasks ORDER BY RAND() LIMIT 2;"
        cursor.execute(query)
        tasks = cursor.fetchall()

        cursor.close()
        conn.close()

        return {"tasks": tasks}
    except mysql.connector.Error as e:
        print(f"MySQL error: {str(e)}")
        raise HTTPException(status_code=500, detail="Database error occurred.")
    except Exception as e:
        print(f"Error in /daily_tasks/: {str(e)}")
        raise HTTPException(status_code=500, detail="An error occurred.")

@app.post("/complete_task/")
def complete_task(task_id: int = Query(...), is_completed: bool = Query(...)):
    """
    Endpoint to update task completion status.
    """
    try:
        conn = connection_pool.get_connection()
        cursor = conn.cursor()

        query = "UPDATE daily_tasks SET is_completed = %s WHERE id = %s"
        cursor.execute(query, (is_completed, task_id))
        conn.commit()

        cursor.close()
        conn.close()

        return {"message": "Task status updated successfully."}
    except mysql.connector.Error as e:
        print(f"MySQL error: {str(e)}")
        raise HTTPException(status_code=500, detail="Database error occurred.")
    except Exception as e:
        print(f"Error in /complete_task/: {str(e)}")
        raise HTTPException(status_code=500, detail="An error occurred.")

@app.post("/daily_meals/")
def add_to_daily_meals(user_id: int = Query(...), recipe_id: int = Query(...)):
    """
    Adds a recipe to the user's daily meals.
    """
    try:
        conn = connection_pool.get_connection()
        cursor = conn.cursor()

        query = """
        INSERT IGNORE INTO daily_meals (user_id, recipe_id, meal_date)
        VALUES (%s, %s, CURRENT_DATE())
        """
        cursor.execute(query, (user_id, recipe_id))
        conn.commit()

        if cursor.rowcount > 0:
            message = "Meal successfully added to daily meals."
        else:
            message = "This meal is already in your daily meals."

        cursor.close()
        conn.close()
        return {"message": message}

    except mysql.connector.Error as e:
        print(f"MySQL error: {str(e)}")
        raise HTTPException(status_code=500, detail="Database error occurred.")
    except Exception as e:
        print(f"Error in /daily_meals/: {str(e)}")
        raise HTTPException(status_code=500, detail="An error occurred.")

@app.get("/daily_meals/")
def get_daily_meals(user_id: int = Query(...)):
    """
    Returns the user's daily meals.
    """
    try:
        conn = connection_pool.get_connection()
        cursor = conn.cursor(dictionary=True)

        query = """
        SELECT r.recipe_id, r.recipe_name, r.recipe_calories, r.recipe_fat_content,
               r.recipe_sugar_content, r.recipe_protein_content, r.recipe_carbohydrate_content
        FROM daily_meals dm
        JOIN recipes r ON dm.recipe_id = r.recipe_id
        WHERE dm.user_id = %s AND dm.meal_date = CURRENT_DATE()
        """
        cursor.execute(query, (user_id,))
        daily_meals = cursor.fetchall()

        cursor.close()
        conn.close()

        if not daily_meals:
            return {"message": "No meal records found for today.", "daily_meals": []}

        return {"message": "Today's meals successfully retrieved.", "daily_meals": daily_meals}

    except mysql.connector.Error as e:
        print(f"MySQL error: {str(e)}")
        raise HTTPException(status_code=500, detail="Database error occurred.")
    except Exception as e:
        print(f"Error in /daily_meals/: {str(e)}")
        raise HTTPException(status_code=500, detail="An error occurred.")

@app.post("/add_to_daily_meals/")
def add_to_daily_meals(user_id: int, recipe_id: int):
    """
    Adds a recipe to the user's daily meals.
    """
    try:
        conn = connection_pool.get_connection()
        cursor = conn.cursor()
        
        query = """
        INSERT INTO daily_meals (user_id, recipe_id, meal_date)
        VALUES (%s, %s, CURRENT_DATE())
        """
        cursor.execute(query, (user_id, recipe_id))
        conn.commit()
        
        cursor.close()
        conn.close()
        return {"message": "Recipe successfully added to Daily."}
    except mysql.connector.Error as e:
        raise HTTPException(status_code=500, detail="Database error occurred.")
    except Exception as e:
        raise HTTPException(status_code=500, detail="An error occurred.")

@app.get("/daily_totals/")
def get_daily_totals(user_id: int):
    """
    Returns the daily total nutritional values for the user.
    """
    try:
        conn = connection_pool.get_connection()
        cursor = conn.cursor(dictionary=True)
        
        query = """
        SELECT 
            SUM(r.recipe_calories) AS total_calories,
            SUM(r.recipe_fat_content) AS total_fat,
            SUM(r.recipe_protein_content) AS total_protein,
            SUM(r.recipe_carbohydrate_content) AS total_carbohydrates
        FROM daily_meals dm
        JOIN recipes r ON dm.recipe_id = r.recipe_id
        WHERE dm.user_id = %s AND dm.meal_date = CURRENT_DATE()
        """
        cursor.execute(query, (user_id,))
        totals = cursor.fetchone()
        
        cursor.close()
        conn.close()
        
        if not totals:
            return {"message": "No meals recorded for today.", "totals": {}}
        
        return {"message": "Today's total values", "totals": totals}
    except mysql.connector.Error as e:
        raise HTTPException(status_code=500, detail="Database error occurred.")
    except Exception as e:
        raise HTTPException(status_code=500, detail="An error occurred.")



def get_feedback_data():
    """
    Feedback verilerini MySQL üzerinden çekip DataFrame olarak döndürür.
    """
    try:
        connection = connect(**db_config)
        query = """
        SELECT 
            uf.user_id,
            u.name,
            uf.disease_id, 
            d.disease_name, 
            uf.recipe_id, 
            r.recipe_name, 
            uf.rating
        FROM user_feedback uf
        JOIN users u ON uf.user_id = u.user_id
        JOIN diseases d ON uf.disease_id = d.id
        JOIN recipes r ON uf.recipe_id = r.recipe_id
        """
        df = pd.read_sql(query, connection)
        connection.close()
        return df
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Feedback verisi alınırken hata: {e}")

def recommend_recipes_by_disease(feedback_df: pd.DataFrame, user_id: int, top_n: int = 5) -> pd.DataFrame:
    """
    Belirtilen kullanıcı için, aynı hastalığa sahip kullanıcıların verdiği yüksek puanlı tarifleri önerir.
    Adımlar:
    1. Hedef kullanıcının hastalığını feedback_df üzerinden belirle.
    2. Aynı hastalığa sahip tüm kullanıcıların geri bildirimlerini filtrele.
    3. Her tarif için ortalama rating ve toplam oy sayısını hesapla.
    4. Hedef kullanıcının daha önce puanlamadığı tarifleri listele.
    5. Ortalama rating'e göre en yüksek puanlı tarifleri döndür.
    """
    user_feedback = feedback_df[feedback_df['user_id'] == user_id]
    if user_feedback.empty:
        raise HTTPException(status_code=404, detail="Kullanıcıya ait geri bildirim bulunamadı.")
    
    
    user_disease = user_feedback.iloc[0]['disease_id']
    
    
    disease_feedback = feedback_df[feedback_df['disease_id'] == user_disease]
    
    
    recipe_stats = (
        disease_feedback
        .groupby(['recipe_id', 'recipe_name'])
        .agg(avg_rating=('rating', 'mean'),
             rating_count=('rating', 'count'))
        .reset_index()
    )
    
    
    user_rated_recipes = set(user_feedback['recipe_id'].unique())
    recipe_stats = recipe_stats[~recipe_stats['recipe_id'].isin(user_rated_recipes)]
    
    
    recipe_stats = recipe_stats.sort_values(['avg_rating', 'rating_count'], ascending=False)
    
    
    top_recipes = recipe_stats.head(top_n)
    
    
    random_recipes = random.sample(top_recipes.to_dict(orient="records"), k=3)
    
    return random_recipes

@app.get("/recommendation_disease_based/")
def recommendation_disease_based(
    user_id: int = Query(..., description="Kullanıcı ID'si"),
    top_n: int = Query(5, description="Öneri sayısı")
):
    try:
        feedback_df = get_feedback_data()
        recommendations = recommend_recipes_by_disease(feedback_df, user_id, top_n=top_n)
        return {"recommended_recipes": recommendations}
    except Exception as e:
        print(f"Error in /recommendation_disease_based/: {str(e)}")
        raise HTTPException(status_code=500, detail=f"An error occurred: {e}")


from semantic_search import SemanticSearch

def fetch_categories():
    conn = connection_pool.get_connection()
    cursor = conn.cursor()
    cursor.execute("SELECT DISTINCT category_of_food FROM food_category LIMIT 20")  
    rows = cursor.fetchall()
    cursor.close()
    conn.close()
    categories = [row[0].lower() for row in rows]
    print(f"Fetched categories: {categories[:10]}...")  
    return categories


def fetch_recipes_for_embedding():
    conn = connection_pool.get_connection()
    cursor = conn.cursor()
    
    cursor.execute("""
        SELECT r.recipe_id, r.recipe_name, r.recipe_ingredients, GROUP_CONCAT(fc.category_of_food)
        FROM recipes r
        LEFT JOIN food_category fc ON r.recipe_id = fc.recipe_id
        GROUP BY r.recipe_id
    """)
    rows = cursor.fetchall()
    cursor.close()
    conn.close()

    if not rows:
        raise ValueError("No recipes found in the database.")
    
    recipe_ids = []
    recipe_texts = []

    for row in rows:
        recipe_ids.append(row[0])
        combined_text = f"{row[1]} {row[2]} {row[3]}"
        recipe_texts.append(combined_text)

    if not recipe_texts:
        raise ValueError("No recipes available for semantic search.")
    
    print(f"Fetched {len(recipe_texts)} recipe texts for embedding.")
    return recipe_texts, recipe_ids

@app.get("/semantic-search")
def semantic_recipe_search(query: str = Query(...)):
    try:
        
        categories = fetch_categories()

        
        if not categories:
            raise ValueError("No categories found in the database.")
        
        
        category_filter = None
        for category in categories:
            if category in query.lower():
                category_filter = category
                break

        
        conn = connection_pool.get_connection()
        cursor = conn.cursor()

        if category_filter:  
            cursor.execute("""
                SELECT r.recipe_id, r.recipe_name, r.recipe_ingredients
                FROM recipes_new r
                JOIN food_category fc ON r.recipe_id = fc.recipe_id
                WHERE LOWER(fc.category_of_food) = %s
            """, (category_filter.lower(),))
        else:  
            cursor.execute("""
                SELECT r.recipe_id, r.recipe_name, r.recipe_ingredients
                FROM recipes_new r
            """)

        rows = cursor.fetchall()
        cursor.close()
        conn.close()

        if not rows:
            raise ValueError("No recipes found in the database.")

        recipe_ids = []
        recipe_texts = []

        for row in rows:
            recipe_ids.append(row[0])
            combined_text = f"{row[1]} {row[2]}"
            recipe_texts.append(combined_text)

        if not recipe_texts:
            raise ValueError("No recipes available for semantic search.")
        
        semantic_engine = SemanticSearch(recipe_texts, recipe_ids, connection_pool)
        results = semantic_engine.search(query, top_k=5, category_filter=category_filter)

        if not results:
            return {
                "message": "No recipes found matching the semantic search.",
                "results": []
            }

        return {
            "message": f"Semantic search results for {query}:",
            "total_results": len(results),
            "results": results
        }

    except Exception as e:
        return {
            "message": "An error occurred while processing the search request.",
            "error": str(e)
        }
    


load_dotenv()

embeddings = HuggingFaceEmbeddings(model_name="sentence-transformers/all-MiniLM-L6-v2")

def load_faiss_index():
    conn = connection_pool.get_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM recipes")
    rows = cursor.fetchall()
    cursor.close()
    conn.close()

    docs = []
    for row in rows:
        text = f"{row['recipe_name']}\nIngredients: {row['recipe_ingredients']}\nInstructions: {row['recipe_recipe_instructions']}"
        metadata = {
            "id": row["recipe_id"],
            "name": row["recipe_name"],
            "disease_tags": row.get("recipe_disease_tags", ""),
            "ingredients": row["recipe_ingredients"]
        }
        docs.append(Document(page_content=text, metadata=metadata))
    return FAISS.from_documents(docs, embeddings)

db = load_faiss_index()

def filter_banned(recipes, user_diseases):
    conn = connection_pool.get_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT banned_product FROM bannedproducts WHERE disease_id IN (%s)" % ",".join(["%s"] * len(user_diseases)), user_diseases)
    banned_rows = cursor.fetchall()
    cursor.close()
    conn.close()

    banned_items = {row["banned_product"].lower() for row in banned_rows}
    return [r for r in recipes if not any(item in r.metadata["ingredients"].lower() for item in banned_items)]

def query_recipes(user_query: str, user_diseases: List[int]):
    retriever = db.as_retriever()
    results = retriever.get_relevant_documents(user_query)
    filtered = filter_banned(results, user_diseases)
    
    if not filtered:
        return {
            "text": "Sorry, I couldn't find a suitable recipe for you.",
            "recipe": None
        }

    recipe_embeddings = embeddings.embed_documents([doc.page_content for doc in filtered])
    user_query_embedding = embeddings.embed_documents([user_query])[0]
    similarities = cosine_similarity([user_query_embedding], recipe_embeddings)

    for i, recipe in enumerate(filtered):
        recipe.metadata["similarity_score"] = similarities[0][i]
    
    sorted_recipes = sorted(filtered, key=lambda x: x.metadata["similarity_score"], reverse=True)
    top = sorted_recipes[0]

    return {
        "text": f"I found a recipe that might suit you: {top.metadata['name']}",
        "recipe": {
            "name": top.metadata["name"],
            "ingredients": top.metadata["ingredients"],
            "instructions": top.page_content.split("Instructions:")[-1].strip()
        }
    }

class UserRequest(BaseModel):
    user_id: int
    user_query: str

@app.post("/get-recipes")
async def get_recipes(request: UserRequest):
    disease = get_user_disease(request.user_id)
    response = query_recipes(request.user_query, [disease])
    return {"response": response}
@app.get("/search_by_ingredients/")
def search_by_ingredients(
    ingredients: str = Query(..., description="Comma-separated list of ingredients"),
    limit: int = Query(10)
):
    try:
        ingredient_list = [i.strip().lower() for i in ingredients.split(",")]

        conn = connection_pool.get_connection()
        cursor = conn.cursor(dictionary=True)

        conditions = []
        values = []
        for ing in ingredient_list:
            like_term = f"%{ing}%"
            conditions.append("(LOWER(recipe_ingredients) LIKE %s OR LOWER(recipe_name) LIKE %s)")
            values.extend([like_term, like_term])

        where_clause = " OR ".join(conditions)
        query = f"""
            SELECT recipe_id, recipe_name, recipe_ingredients, recipe_recipe_instructions, 
                   recipe_calories, recipe_fat_content, recipe_protein_content, 
                   recipe_sugar_content, recipe_carbohydrate_content
            FROM recipes
            WHERE {where_clause}
            LIMIT %s
        """

        values.append(limit)
        cursor.execute(query, values)
        results = cursor.fetchall()
        cursor.close()
        conn.close()

        return {
            "results": results,
            "total_results": len(results),
            "message": f"Matching: {', '.join(ingredient_list)}"
        }

    except Exception as e:
        print("Error in /search_by_ingredients/:", e)
        raise HTTPException(status_code=500, detail="Ingredient search failed.")

MODEL_PATH = "./saved_model_large"  
model = T5ForConditionalGeneration.from_pretrained(MODEL_PATH)
tokenizer = T5Tokenizer.from_pretrained(MODEL_PATH)

device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
model.to(device)

class RecipeRequest(BaseModel):
    name: str
    ingredients: str

def generate_recipe(name, ingredients):
    input_text = f"<|startoftext|>{name}\nIngredients: {ingredients}"
    input_ids = tokenizer(input_text, return_tensors="pt").input_ids.to(device)

    output_ids = model.generate(
        input_ids,
        max_length=150,
        do_sample=True,
        top_k=30,
        top_p=0.90,
        temperature=0.7,
        no_repeat_ngram_size=4,
        repetition_penalty=1.6,
    )

    return tokenizer.decode(output_ids[0], skip_special_tokens=True)

@app.post("/generate-recipe")
def get_recipe(request: RecipeRequest):
    try:
        result = generate_recipe(request.name, request.ingredients)
        return {
            "recipe_name": request.name,
            "ingredients": request.ingredients,
            "generated_recipe": result
        }
    except Exception as e:
        return {"error": str(e)}
    
import logging
    
@app.get("/rag_recommendation")
def rag_recommendation(
    user_query: str = Query(..., description="Kullanıcının yemek sorgusu"),
    user_id: int = Query(..., description="Kullanıcının veritabanı ID'si")
):
    try:
        result = query_recipes_rag(user_query, user_id, db)
        return {"recommendation": result}
    except Exception as e:
        logging.exception("RAG öneri hatası")
        raise HTTPException(status_code=500, detail="Sunucu hatası: " + str(e))

df_feedback = pd.read_csv('combined_ratings.csv')
df_recipes = df_feedback[['recipe_id', 'recipe_name', 'disease_id']].drop_duplicates()
df_recipes.rename(columns={'disease_id': 'recipe_disease_tags'}, inplace=True)



def generate_user_collaborative_recommendations(df_feedback, df_recipes, user_id, disease_id, top_n=5):
    filtered_df = df_feedback[df_feedback['disease_id'] == disease_id]
    pivot = filtered_df.pivot_table(index='user_id', columns='recipe_id', values='rating').fillna(0)

    if user_id not in pivot.index:
        raise HTTPException(status_code=404, detail=f"Kullanıcı {user_id} bu disease_id={disease_id} grubunda bulunamadı.")
    
    similarity_matrix = cosine_similarity(pivot)
    similarity_df = pd.DataFrame(similarity_matrix, index=pivot.index, columns=pivot.index)

    similar_users = similarity_df[user_id].sort_values(ascending=False).drop(user_id)
    similar_user_ids = similar_users.index.tolist()

    similar_users_ratings = pivot.loc[similar_user_ids]
    recipe_scores = similar_users_ratings.mean(axis=0)

    user_rated_recipes = pivot.loc[user_id]
    recipes_to_recommend = recipe_scores[user_rated_recipes == 0]
    top_recommendations = recipes_to_recommend.sort_values(ascending=False).head(top_n)

    recommendations_df = top_recommendations.reset_index()
    recommendations_df.columns = ['recipe_id', 'score']  
    
    merged = recommendations_df.merge(
        df_recipes[['recipe_id', 'recipe_name', 'recipe_disease_tags']],
        on='recipe_id',
        how='left'
    )
    
    deduped = merged.groupby(['recipe_id', 'recipe_name']).agg({'score': 'max'}).reset_index()
    deduped = deduped.sort_values(by='score', ascending=False).head(top_n)
    return deduped[['recipe_id', 'recipe_name', 'score']]

@app.get("/user_collaborative_filtering/{user_id}")
def user_collaborative_filtering(user_id: int, top_n: int = 5):
    disease_name = get_user_disease(user_id)
    
    disease_mapping = {
        'celiac': 1,
        'diabetes': 3,
        'high cholesterol': 4,
        'lactose intolerance': 8
    }
    if disease_name not in disease_mapping:
        raise HTTPException(status_code=404, detail="Disease not supported.")
    disease_id = disease_mapping[disease_name]
    
    recommendations = generate_user_collaborative_recommendations(df_feedback, df_recipes, user_id, disease_id, top_n)
    
    return recommendations.to_dict(orient='records')

