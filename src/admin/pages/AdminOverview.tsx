import ApiStatus from "../components/ApiStatus";


const modules = [
  {
    title: "Projecten",
    description: "Beheer je portfolio, casestudy's en publicaties.",
    page: "projects",
  },
  {
    title: "Klanten",
    description: "Houd aanvragen, contacten en gespreksnotities bij.",
    page: "clients",
  },
  {
    title: "Agenda",
    description: "Beheer afspraken en je beschikbare tijdsloten.",
    page: "calendar",
  },
  {
    title: "Financiën",
    description: "Beheer offertes, facturen, uren en kosten.",
    page: "finances",
  },
] as const;

type AdminOverviewProps = {
  onNavigate: (
    page: "projects" | "clients" | "calendar" | "finances",
  ) => void;
};

export default function AdminOverview({
  onNavigate,
}: AdminOverviewProps) {
  return (
    <div className="cms-overview">
      <div className="cms-page-heading">
        <p className="hero-label">RGB VISUALS CMS</p>
        <h1>Overzicht</h1>
        <p>Welkom in je centrale werkruimte.</p>
      </div>

      <div className="cms-notice">
        <strong>Ontwikkelversie:</strong> de modules worden stapsgewijs
        aangesloten op de database. Er worden nog geen echte
        bedrijfsgegevens opgeslagen.
      </div>

      <div className="cms-module-grid">
        {modules.map((module) => (
          <button
            key={module.page}
            type="button"
            className="cms-module-card"
            onClick={() => onNavigate(module.page)}
          >
            <h2>{module.title}</h2>
            <p>{module.description}</p>
            <span>Open module →</span>
          </button>
        ))}
      </div>

      <div className="cms-info-panel">
        <h2>Jouw standaard beschikbaarheid</h2>
        <p>Maandag t/m vrijdag · 09:00–17:00</p>
        <p>
          Geplande boekingsinstellingen: gesprekken van 30 minuten,
          15 minuten buffer en minimaal 24 uur vooraf boeken.
        </p>
        <small>
          Dit zijn ontwerpinstellingen. De agenda is nog niet gekoppeld.
        </small>
      </div>
      <ApiStatus />
    </div>
  );
}