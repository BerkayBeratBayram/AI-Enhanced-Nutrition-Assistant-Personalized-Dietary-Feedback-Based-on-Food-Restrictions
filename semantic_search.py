import mysql.connector
from mysql.connector import pooling
from sentence_transformers import SentenceTransformer
import faiss
from textblob import TextBlob
import numpy as np



import pickle  

class SemanticSearch:
    def __init__(self, recipe_texts, recipe_ids, connection_pool, model_path='all-MiniLM-L6-v2'):
        self.model = SentenceTransformer(model_path)
        self.recipe_ids = recipe_ids
        self.recipe_texts = recipe_texts
        self.connection_pool = connection_pool
        
        self.recipe_embeddings = self.create_or_load_embeddings(recipe_texts)
        
        self.index = faiss.IndexFlatL2(self.recipe_embeddings.shape[1])
        self.index.add(self.recipe_embeddings)

    def create_or_load_embeddings(self, texts):
        embeddings_file = 'recipe_embeddings.pkl'
        try:
            with open(embeddings_file, 'rb') as f:
                embeddings = pickle.load(f)
                print("Embeddings loaded from file.")
                return embeddings
        except FileNotFoundError:
            print("Embeddings file not found, creating embeddings...")
            embeddings = self.model.encode(texts, convert_to_numpy=True)
            with open(embeddings_file, 'wb') as f:
                pickle.dump(embeddings, f)
            return embeddings

    def correct_query(self, query):
        return str(TextBlob(query).correct())

    def search(self, query, top_k=5, category_filter=None):
        corrected_query = self.correct_query(query)
        query_embedding = self.model.encode([corrected_query], convert_to_numpy=True)
        D, I = self.index.search(query_embedding, k=top_k)

        results = []
        for i in I[0]:
            recipe_id = self.recipe_ids[i]
            full_info = self.get_recipe_info(recipe_id)

            if full_info:
                if category_filter and category_filter.lower() not in full_info['category_of_food'].lower():
                    continue
                
                if 'sugar' in full_info['recipe_ingredients'].lower():
                    results.append(full_info)

        return results

    def get_recipe_info(self, recipe_id):
        connection = self.connection_pool.get_connection()
        cursor = connection.cursor(dictionary=True)

        try:
            cursor.execute("""
                SELECT r.recipe_id, r.recipe_name, r.recipe_calories, r.recipe_fat_content,
                    r.recipe_carbohydrate_content, r.recipe_sugar_content, r.recipe_protein_content,
                    r.recipe_recipe_instructions, r.recipe_ingredients,
                    c.category_of_food
                FROM recipes_new r
                LEFT JOIN food_category c ON r.recipe_id = c.recipe_id
                WHERE r.recipe_id = %s;
            """, (recipe_id,))

            row = cursor.fetchone()
            
            while cursor.nextset():
                pass
            
            return row
        except Exception as e:
            print(f"Error fetching full recipe info for {recipe_id}: {e}")
            return None
        finally:
            cursor.close()
            connection.close()