# core.py
from sqlalchemy import text
from sqlalchemy.orm import Session
from datetime import datetime, timedelta

class AnalyticsLogic:

    # ==========================================
    # MÉTRICAS ECONÓMICAS
    # ==========================================
    @staticmethod
    def obtener_metricas_economicas(db: Session) -> dict:
        # 1. Volumen (Comisiones del 20%) y Precio Medio de obras ENTREGADAS
        # Solo consideramos ventas completadas (ENTREGADA) para el beneficio real
        q_ventas = text("""
            SELECT 
                SUM(precio_actual * 0.20) as volumen,
                AVG(precio_actual) as precio_medio
            FROM obra
            WHERE estado = 'ENTREGADA'
        """)
        ventas = db.execute(q_ventas).fetchone()
        
        # 2. Estado del mercado (Activas vs Finalizadas/Pendientes de entrega)
        q_mercado = text("""
            SELECT 
                SUM(CASE WHEN estado = 'ACTIVA' THEN 1 ELSE 0 END) as activas,
                SUM(CASE WHEN estado IN ('FINALIZADA', 'ENTREGADA') THEN 1 ELSE 0 END) as finalizadas
            FROM obra
        """)
        mercado = db.execute(q_mercado).fetchone()

        # 3. Capital en Custodia (Fondos bloqueados de los usuarios)
        q_custodia = text("SELECT SUM(saldo_bloqueado) FROM usuario")
        custodia = db.execute(q_custodia).scalar()

        return {
            "volumen_negocio": float(ventas[0] or 0),
            "precio_medio": float(ventas[1] or 0),
            "mercado_activas": int(mercado[0] or 0),
            "mercado_finalizadas": int(mercado[1] or 0),
            "capital_custodia": float(custodia or 0)
        }

    # ==========================================
    # MÉTRICAS DE USUARIOS Y COMUNIDAD
    # ==========================================
    @staticmethod
    def obtener_metricas_usuarios(db: Session) -> dict:
        # Límite de 7 días para los nuevos usuarios
        fecha_limite = datetime.now() - timedelta(days=7)
        
        # 1. Stats Generales de Usuarios
        q_stats = text("""
            SELECT 
                COUNT(*) as total_usuarios,
                SUM(CASE WHEN fecha_registro >= :fecha_limite THEN 1 ELSE 0 END) as nuevos_semana,
                SUM(CASE WHEN es_artista = 1 THEN 1 ELSE 0 END) as total_artistas
            FROM usuario
        """)
        stats = db.execute(q_stats, {"fecha_limite": fecha_limite}).fetchone()

        # 2. Top Mecenas (Compradores de obras ENTREGADAS)
        # Cambiamos FINALIZADA a ENTREGADA para reflejar el gasto real completado
        q_top = text("""
            SELECT u.nombre, SUM(o.precio_actual * 1.12) as total_invertido
            FROM usuario u
            JOIN obra o ON u.id_usuario = o.id_comprador
            WHERE o.estado = 'ENTREGADA'
            GROUP BY u.id_usuario, u.nombre
            ORDER BY total_invertido DESC
            LIMIT 5
        """)
        resultados_top = db.execute(q_top).fetchall()
        
        top_mecenas = []
        for r in resultados_top:
            top_mecenas.append({"nombre": r[0], "total_invertido": float(r[1])})

        return {
            "total_usuarios": int(stats[0] or 0),
            "nuevos_semana": int(stats[1] or 0),
            "total_artistas": int(stats[2] or 0),
            "top_mecenas": top_mecenas
        }