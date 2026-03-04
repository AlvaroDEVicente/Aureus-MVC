# api.py
from fastapi import FastAPI, Depends
from fastapi.middleware.cors import CORSMiddleware
from sqlalchemy.orm import Session

# Importaciones locales
from database import get_db
from core import AnalyticsLogic

app = FastAPI(title="AUREUS Analytics API", description="Microservicio OLAP para Dashboard de Administración")

# Configuración de CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"], # En producción, restringir al dominio de AUREUS
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# --- Endpoints de Analytics ---

@app.get("/api/analytics/dashboard")
def get_dashboard_data(db: Session = Depends(get_db)):
    """
    Devuelve un JSON estructurado por dominios (Económico y Usuarios)
    """
    return {
        "success": True,
        "data": {
            "economico": AnalyticsLogic.obtener_metricas_economicas(db),
            "usuarios": AnalyticsLogic.obtener_metricas_usuarios(db)
        }
    }