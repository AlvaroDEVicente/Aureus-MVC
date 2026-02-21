# 🏛️ AUREUS - Plataforma de Subastas de Arte y Mecenazgo Digital

**AUREUS** es un Proyecto Intermodular desarrollado para el CFGS de Desarrollo de Aplicaciones Web (2º DAW). Inspirada en el mecenazgo romano y bajo una estética *Dark Luxury*, la plataforma permite a mecenas y artistas interactuar en un mercado de subastas de arte digital y clásico en tiempo real.

## 👥 Equipo de Desarrollo

- **Alvarus** - Frontend, UX/UI y SPA.
- **Robertus** - Backend, Arquitectura de Datos y Transacciones ACID.

## 🛠️ Stack Tecnológico y Arquitectura

El proyecto se fundamenta en una arquitectura **MVC (Modelo-Vista-Controlador)** pura, separando claramente las responsabilidades:

- **Frontend (Cliente):** HTML5, CSS3 (Custom Properties), JavaScript (ES6+).
  - *Librerías:* Chart.js (evolución de valor), Tabulator (registros de bóveda), EasyTimer.js, noUiSlider.
  - *Enfoque:* Single Page Application (SPA) con Fetch API. Renderizado dinámico y lógica UX adaptativa para temporizadores de larga duración.
- **Backend (Servidor):** PHP (Sin frameworks pesados).
  - *Enrutamiento:* Patrón Front Controller (`index.php`).
  - *Rendimiento:* Sistema de **Caché de Archivos (File Caching)** para el *Ticker* global, reduciendo el consumo de CPU del servidor en un 99% frente a peticiones concurrentes (*polling*).
- **Base de Datos:** MySQL / MariaDB.
  - *Concurrencia:* Consultas Preparadas y Bloqueos Pesimistas (`SELECT ... FOR UPDATE` en transacciones ACID) para evitar *Race Conditions* y saldos fantasma.
  - *Seguridad Anti-Sniping:* Reglas automáticas delegadas al motor SQL que extienden la vida de la subasta en 5 minutos si se detecta una puja de "francotirador" en los últimos instantes.
- **Servicios Auxiliares:** Python (Microservicio de automatización - *En desarrollo*).

## 🚀 Instalación y Despliegue en Local (Entorno de Desarrollo)

Para levantar la bóveda de Aureus en tu máquina local, sigue estos pasos:

1. **Clonar el repositorio:**
   ```bash
   git clone [https://github.com/AlvaroDEVicente/Aureus-MVC.git](https://github.com/AlvaroDEVicente/Aureus-MVC.git)
   cd AureusMVC