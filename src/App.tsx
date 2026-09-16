import Navbar from "./components/Navbar";
import Hero from "./sections/Hero";
import Projects from "./sections/Projects";
import ProjectDetail from "./sections/ProjectDetail";
import AdminDashboard from "./admin/AdminDashboard";
import { projects } from "./data/projects";

import "./App.css";

function App() {
  const params = new URLSearchParams(window.location.search);
  const projectId = params.get("project");
  const adminPreview = params.get("admin") === "preview";

  if (adminPreview && import.meta.env.DEV) {
    return (
      <main>
        <AdminDashboard />
      </main>
    );
  }

  const selectedProject = projects.find(
    (project) =>
      project.status === "published" && String(project.id) === projectId,
  );

  if (projectId !== null) {
    return (
      <>
        <Navbar />

        <main>
          {selectedProject ? (
            <ProjectDetail project={selectedProject} />
          ) : (
            <section className="project-not-found">
              <h1>Project niet gevonden</h1>
              <a href="/">Terug naar portfolio</a>
            </section>
          )}
        </main>
      </>
    );
  }

  return (
    <>
      <Navbar />

      <main>
        <Hero />
        <Projects />
      </main>
    </>
  );
}

export default App;
