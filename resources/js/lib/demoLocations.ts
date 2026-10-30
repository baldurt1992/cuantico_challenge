export interface LocationPoint {
    latitude: number;
    longitude: number;
    label: string;
}
export const demoLocations = [
    {
        id: "museo-oro",
        name: "Museo del Oro",
        address: "Carrera 6 # 15-88, Bogotá",
        latitude: 4.601919,
        longitude: -74.072,
        source: "https://www.wikidata.org/wiki/Q1109031",
    },
    {
        id: "palacio-lievano",
        name: "Palacio Liévano",
        address: "Carrera 8 # 10-65, Bogotá",
        latitude: 4.598414,
        longitude: -74.076539,
        source: "https://www.wikidata.org/wiki/Q3360886",
    },
    {
        id: "parque-simon",
        name: "Parque Simón Bolívar",
        address: "Parque metropolitano, Bogotá",
        latitude: 4.658056,
        longitude: -74.093889,
        source: "https://www.wikidata.org/wiki/Q3376660",
    },
];
