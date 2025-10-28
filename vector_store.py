from database import get_connection
from langchain_community.vectorstores import FAISS
from langchain.schema import Document
from langchain.embeddings import HuggingFaceEmbeddings

def load_documents():
    conn = get_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM recipes")
    rows = cursor.fetchall()
    cursor.close()
    conn.close()

    docs = []
    for row in rows:
        content = f"{row['recipe_name']}\nIngredients: {row['recipe_ingredients']}\nInstructions: {row['recipe_recipe_instructions']}"
        metadata = {
            "id": row["recipe_id"],
            "name": row["recipe_name"],
            "ingredients": row["recipe_ingredients"]
        }
        docs.append(Document(page_content=content, metadata=metadata))
    return docs

_vector_store = None

def get_vector_store():
    global _vector_store
    if _vector_store is None:
        embeddings = HuggingFaceEmbeddings(model_name="sentence-transformers/all-MiniLM-L6-v2")
        docs = load_documents()
        _vector_store = FAISS.from_documents(docs, embeddings)
    return _vector_store
