
export type AdminPage =
  | "overview"
  | "projects"
  | "clients"
  | "calendar"
  | "finances"
  | "settings";

type AdminSidebarProps = {
  activePage: AdminPage;
  onNavigate: (page: AdminPage) => void;
};

const menuItems: { id: AdminPage; label: string; icon: string }[] = [
  { id: "overview", label: "Overzicht", icon: "◫" },
  { id: "projects", label: "Projecten", icon: "▣" },
  { id: "clients", label: "Klanten", icon: "♙" },
  { id: "calendar", label: "Agenda", icon: "▦" },
  { id: "finances", label: "Financiën", icon: "€" },
  { id: "settings", label: "Instellingen", icon: "⚙" },
];

export default function AdminSidebar({
  activePage,
  onNavigate,
}: AdminSidebarProps) {
  return (
    <aside className="cms-sidebar">
      <div className="cms-brand">
        <span className="cms-brand-mark">RGB</span>
        <div>
          <strong>RGB Visuals</strong>
          <small>Control Center</small>
        </div>
      </div>

      <p className="cms-menu-label">WERKRUIMTE</p>

      <nav className="cms-navigation" aria-label="Dashboardnavigatie">
        {menuItems.map((item) => (
          <button
            key={item.id}
            type="button"
            className={
              activePage === item.id
                ? "cms-nav-item cms-nav-item-active"
                : "cms-nav-item"
            }
            aria-current={activePage === item.id ? "page" : undefined}
            onClick={() => onNavigate(item.id)}
          >
            <span aria-hidden="true">{item.icon}</span>
            {item.label}
          </button>
        ))}
      </nav>

      <a className="cms-portfolio-link" href="/">
        ← Bekijk portfolio
      </a>
    </aside>
  );
}