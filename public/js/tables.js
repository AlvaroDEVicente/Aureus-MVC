import { TabulatorFull as Tabulator } from "https://cdn.jsdelivr.net/npm/tabulator-tables@5.5.0/+esm";

export const tablas = {
  crearTicker: (id, datos) =>
    new Tabulator(id, {
      data: datos,
      layout: "fitColumns",
      height: "auto", // Un pelín más alto para rellenar el hueco
      columns: [
        // 1. Damos todo el espacio dinámico al título de la obra
        { title: "Obra", field: "titulo_obra", widthGrow: 1 },

        // 2. Columna de Dinero: Ancho fijo, alineada a la derecha y título corto
        {
          title: "Valor",
          field: "monto",
          width: 110,
          hozAlign: "right",
          headerHozAlign: "right",
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

  crearAdminUsuarios: (id, datos, cbCambioRol, cbBorrar, cbAmnistiar) =>
    new Tabulator(id, {
      data: datos,
      layout: "fitColumns",
      columns: [
        { title: "ID", field: "id_usuario", width: 60 },
        {
          title: "Nombre",
          field: "nombre",
          widthGrow: 2,
          // Si está baneado (activo=0), tachamos el nombre
          formatter: (cell) =>
            cell.getData().activo == 0
              ? `<span style="text-decoration: line-through; color: #888;">${cell.getValue()}</span>`
              : cell.getValue(),
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
          title: "Acción",
          formatter: (cell) => {
            if (cell.getData().activo == 1) {
              return `<button class="btn-outline" style="padding: 2px 8px; font-size: 0.8rem; color: #dc3545; border-color: #dc3545;">Desterrar</button>`;
            } else {
              return `<button class="btn-outline" style="padding: 2px 8px; font-size: 0.8rem; color: #28a745; border-color: #28a745;">Amnistiar</button>`;
            }
          },
          hozAlign: "center",
          headerSort: false,
          cellClick: (e, cell) => {
            if (cell.getData().activo == 1) {
              cbBorrar(cell.getData().id_usuario);
            } else {
              cbAmnistiar(cell.getData().id_usuario);
            }
          },
        },
      ],
    }),
};
