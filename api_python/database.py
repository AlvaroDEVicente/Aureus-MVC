from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker, declarative_base

# Configuración de la conexión a MySQL (XAMPP)
# URL: mysql+mysqlconnector://<usuario>:<password>@<host>/<database>
# database.py
URL_DATABASE = "mysql+mysqlconnector://root@localhost/aureus_db"

# Motor de base de datos
engine = create_engine(URL_DATABASE)

# Fábrica de sesiones
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

# Clase base para los modelos
Base = declarative_base()

# Función para obtener la sesión de BD
def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
