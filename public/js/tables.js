import { TabulatorFull as Tabulator } from "https://cdn.jsdelivr.net/npm/tabulator-tables@5.5.0/+esm";

export const tablas = {
  crearTicker: (id, datos) =>
    new Tabulator(id, {
      data: datos,
      layout: "fitColumns",
      height: "300px",
      columns: [
        { title: "Obra", field: "titulo_obra", widthGrow: 3 },
        { title: "Mecenas", field: "nombre_usuario", widthGrow: 2 },
        {
          title: "Monto",
          field: "monto",
          formatter: "money",
          formatterParams: { symbol: "€" },
        },
      ],
    }),

  crearHistorialObra: (id, datos) =>
    new Tabulator(id, {
      data: datos,
      layout: "fitColumns",
      columns: [
        { title: "Mecenas", field: "nombre_usuario" },
        {
          title: "Monto",
          field: "monto",
          formatter: "money",
          formatterParams: { symbol: "€" },
        },
      ],
    }),

  crearTaller: (id, datos) =>
    new Tabulator(id, {
      data: datos,
      layout: "fitColumns",
      columns: [
        { title: "Obra", field: "titulo", widthGrow: 2 },
        {
          title: "Precio Actual",
          field: "precio_actual",
          formatter: "money",
          formatterParams: { symbol: "€" },
        },
        { title: "Estado", field: "estado", hozAlign: "center" },
      ],
    }),

  crearBoveda: (id, datos) =>
    new Tabulator(id, {
      data: datos,
      layout: "fitColumns",
      placeholder: "Aún no has participado en ninguna subasta.",
      columns: [
        { title: "Obra", field: "titulo", widthGrow: 2 },
        {
          title: "Mi Puja",
          field: "mi_monto",
          formatter: "money",
          formatterParams: { symbol: "€" },
        },
        { title: "Estado", field: "estado_puja" },
      ],
    }),

  crearAdminPendientes: (id, datos, cbAprobar) =>
    new Tabulator(id, {
      data: datos,
      layout: "fitColumns",
      placeholder: "No hay obras pendientes.",
      columns: [
        { title: "Obra", field: "titulo", widthGrow: 2 },
        { title: "Artista", field: "nombre_artista", widthGrow: 2 },
        {
          title: "Precio Salida",
          field: "precio_inicial",
          formatter: "money",
          formatterParams: { symbol: "€" },
        },
        // ¡Magia de eventos aquí! En vez de onclick, pasamos el callback.
        {
          title: "Acción",
          formatter: () =>
            `<button class="btn-gold" style="padding: 5px 10px; font-size: 0.8rem;">Validar</button>`,
          hozAlign: "center",
          headerSort: false,
          cellClick: (e, cell) => cbAprobar(cell.getData().id_obra),
        },
      ],
    }),

  crearAdminUsuarios: (id, datos, cbCambioRol, cbBorrar) =>
    new Tabulator(id, {
      data: datos,
      layout: "fitColumns",
      columns: [
        { title: "ID", field: "id_usuario", width: 60 },
        {
          title: "Nombre",
          field: "nombre",
          widthGrow: 2,
          // Formateador dinámico: Si está baneado, lo tachamos y le ponemos insignia
          formatter: (cell) => {
            const usuario = cell.getData();
            if (usuario.activo == 0) {
              return `<span class="text-danger text-decoration-line-through">${usuario.nombre}</span> <span class="badge bg-danger ms-2">Desterrado</span>`;
            }
            return usuario.nombre;
          },
        },
        { title: "Email", field: "email", widthGrow: 2 },
        {
          title: "Saldo",
          field: "saldo_disponible",
          formatter: "money",
          formatterParams: { symbol: "€" },
        },
        {
          title: "Rol",
          field: "rol",
          editor: "list",
          editorParams: {
            values: {
              visitante: "Visitante",
              comprador: "Comprador",
              artista: "Artista",
              admin: "Admin",
            },
          },
          cellEdited: (cell) => cbCambioRol(cell.getData()),
        },
        {
          title: "Expulsar",
          // Formateador: Si ya está baneado, quitamos el botón
          formatter: (cell) => {
            const usuario = cell.getData();
            if (usuario.activo == 0) {
              return `<span class="text-muted small">Inhabilitado</span>`;
            }
            return `<button class="btn-outline" style="padding: 2px 8px; font-size: 0.8rem; color: #dc3545; border-color: #dc3545;">Borrar</button>`;
          },
          hozAlign: "center",
          headerSort: false,
          // Evento: Solo lanzamos el borrado si está activo
          cellClick: (e, cell) => {
            if (cell.getData().activo != 0) {
              cbBorrar(cell.getData().id_usuario);
            }
          },
        },
      ],
    }),
};
