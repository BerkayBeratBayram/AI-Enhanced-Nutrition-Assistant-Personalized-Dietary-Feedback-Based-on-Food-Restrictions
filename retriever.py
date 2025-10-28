from time import time  
from database import get_connection
from langchain_ollama import OllamaLLM
from langchain_community.llms import Ollama
from sklearn.metrics.pairwise import cosine_similarity
from langchain_community.embeddings import HuggingFaceEmbeddings
from vector_store import get_vector_store

embedding_model = HuggingFaceEmbeddings(model_name="sentence-transformers/all-MiniLM-L6-v2")
llm_model = OllamaLLM(model="mistral")
def get_user_disease(user_id):
    conn = get_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT disease FROM users WHERE user_id = %s", (user_id,))
    result = cursor.fetchone()
    cursor.close()
    conn.close()
    if not result:
        return None
    return result["disease"].lower()

def get_banned_items(disease_name):
    conn = get_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT banned_product FROM bannedproducts 
        JOIN diseases ON bannedproducts.disease_id = diseases.id 
        WHERE diseases.disease_name = %s
    """, (disease_name,))
    rows = cursor.fetchall()
    cursor.close()
    conn.close()
    return {r['banned_product'].lower() for r in rows}

def query_recipes_rag(user_query, user_id, db):
    print(f"query_recipes called with: user_query={user_query}, user_id={user_id}, db={type(db)}")

    retriever = db.as_retriever()


    start = time()
    results = retriever.get_relevant_documents(user_query)
    print(f"Retrieval took {time() - start:.2f} seconds")


    disease = get_user_disease(user_id)
    if not disease:
        return "User disease not found."


    banned_items = get_banned_items(disease)


    start = time()
    filtered = []
    for doc in results:
        ingredients = doc.metadata['ingredients'].lower()
        if not any(item in ingredients for item in banned_items):
            filtered.append(doc)
    print(f"Filtering took {time() - start:.2f} seconds")

    if not filtered:
        return "No suitable recipes found for your health condition."

 
    start = time()
    docs_embeddings = embedding_model.embed_documents([doc.page_content for doc in filtered])
    query_emb = embedding_model.embed_documents([user_query])[0]
    print(f"Embedding took {time() - start:.2f} seconds")

    sims = cosine_similarity([query_emb], docs_embeddings)[0]
    for i, doc in enumerate(filtered):
        doc.metadata['similarity'] = sims[i]

    top_docs = sorted(filtered, key=lambda x: x.metadata['similarity'], reverse=True)[:1]
    context = "\n\n".join([doc.page_content for doc in top_docs])


    prompt = f"""
    The user asked: '{user_query}'
    The user has the disease: '{disease}'

    Regardless of what the user asks, if the request is unrelated to food, ask the user to provide a food item instead.
    You are a dietician, you give brief and short instructions to the user. Considering the user's disease, suggest appropriate and safe recipes from below:

    {context}
    """

    start = time()
    response = llm_model.invoke(prompt)
    print(f"LLM response took {time() - start:.2f} seconds")

    return response
