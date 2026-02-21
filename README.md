# 🏛️ AUREUS - Plataforma de Subastas de Arte y Mecenazgo Digital

**AUREUS** es un Proyecto Intermodular desarrollado para el CFGS de Desarrollo de Aplicaciones Web (2º DAW). Inspirada en el mecenazgo romano y bajo una estética _Dark Luxury_, la plataforma permite a mecenas y artistas interactuar en un mercado de subastas de arte digital y clásico en tiempo real.

## 👥 Equipo de Desarrollo

- **Alvarus** - Frontend, UX/UI y SPA.
- **Robertus** - Backend, Arquitectura de Datos y Transacciones ACID.

## 🛠️ Stack Tecnológico

El proyecto se fundamenta en una arquitectura **MVC (Modelo-Vista-Controlador)** pura, separando claramente las responsabilidades:

- **Frontend (Cliente):** HTML5, CSS3 (Custom Properties), JavaScript (ES6+).
  - _Librerías:_ Chart.js (evolución de valor), Tabulator (registros de bóveda), EasyTimer.js, noUiSlider.
  - _Enfoque:_ Single Page Application (SPA) con Fetch API.
- **Backend (Servidor):** PHP (Sin frameworks pesados).
  - _Enrutamiento:_ Front Controller pattern (`index.php`).
- **Base de Datos:** MySQL / MariaDB.
  - _Seguridad:_ Consultas Preparadas (MySQLi Orientado a Objetos) y Bloqueos Pesimistas (`FOR UPDATE`) para evitar _Race Conditions_ en la concurrencia de pujas.
- **Servicios Auxiliares:** Python (Microservicio de automatización - _En desarrollo_).

## 🚀 Instalación y Despliegue en Local (Entorno de Desarrollo)

Para levantar la bóveda de Aureus en tu máquina local, sigue estos pasos:

1. **Clonar el repositorio:**
   ```bash
   git clone [https://github.com/TU_USUARIO/Aureus.git](https://github.com/TU_USUARIO/Aureus.git)
   cd Aureus
   ```
