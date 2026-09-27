import type { CompanyRef } from '../../../types/contracts';

export type CatalogCompany = CompanyRef & {
    city: string | null;
    isRemote: boolean;
};

// Initials rule, derived once at module load: first letter of each of the
// first two words, uppercase. Single-word names use their first two letters.
const initialsOf = (name: string): string => {
    const words = name.trim().split(/\s+/);

    const raw = words.length > 1 ? words[0][0] + words[1][0] : name.slice(0, 2);

    return raw.toUpperCase();
};

// [name, city or region text, isRemote]. All names are fictional.
const RAW: [string, string | null, boolean][] = [
    ['Klarwerk', 'Berlin', false],
    ['Lumen Health', 'Lisbon', false],
    ['Estrela Pay', 'São Paulo', false],
    ['Nuvia', 'Madrid', false],
    ['Cobalt Freight', 'Remote EU', true],
    ['Pampa Logística', 'Porto Alegre', false],
    ['Brisa Seguros', 'Recife', false],
    ['Norte Analytics', 'Porto', false],
    ['Arcadia Games', 'Barcelona', false],
    ['Fjord Mobility', 'Oslo', false],
    ['Tessera Cloud', 'Dublin', false],
    ['Maré Energia', 'Florianópolis', false],
    ['Solvio', 'Amsterdam', false],
    ['Quanta Retail', 'Remote', true],
    ['Oriol Studio', 'Valencia', false],
    ['Duna Fintech', 'Belo Horizonte', false],
    ['Kestrel Security', 'London', false],
    ['Alto Commerce', 'Remote LATAM', true],
    ['Ribeira Tech', 'Coimbra', false],
    ['Faro Data', 'Remote', true],
    ['Helix Bio', 'Munich', false],
    ['Tinta Media', 'Buenos Aires', false],
    ['Vértice', 'Curitiba', false],
    ['Moraga Systems', 'Bilbao', false],
    ['Kiln', 'Remote US', true],
    ['Sable Robotics', 'Stockholm', false],
    ['Ipê Labs', 'Brasília', false],
    ['Marlow Insurance', 'Manchester', false],
    ['Vela Travel', 'Mexico City', false],
    ['Borealis Energy', 'Toronto', false],
    // Added for the one-application-per-company-per-day rule: today's 33
    // applications need 33 distinct companies and some must stay free.
    ['Copperleaf Systems', 'London', false],
    ['Ashgrove Digital', 'Edinburgh', false],
    ['Pinecrest Analytics', 'Remote', true],
    ['Ondine Software', 'Copenhagen', false],
    ['Zephyra', 'Vienna', false],
    ['Halden Works', 'Zurich', false],
    ['Tallow Bay Games', 'Remote US', true],
    ['Ironbark Payments', 'Sydney', false],
    ['Cais Digital', 'Braga', false],
    ['Sol Nascente Tech', 'Salvador', false],
    ['Ondas Sistemas', 'Fortaleza', false],
    ['Campo Verde Agro', 'Goiânia', false],
    ['Alba Soluciones', 'Sevilla', false],
    ['Cumbre Software', 'Bogotá', false],
];

export const COMPANIES: CatalogCompany[] = RAW.map(
    ([name, city, isRemote], index) => ({
        id: index + 1,
        name,
        initials: initialsOf(name),
        city,
        isRemote,
    }),
);

export const companyByName = (name: string): CatalogCompany => {
    const company = COMPANIES.find((row) => row.name === name);

    if (!company) {
        throw new Error(`Unknown fixture company: ${name}`);
    }

    return company;
};
