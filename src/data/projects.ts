import type { Project } from "../types/project";
import heroImg from "../assets/hero.png";

export const projects: Project[] = [
  {
    id: 1,
    title: "RGB Visuals",
    description: "Mijn eigen portfolio en toekomstige projectbeheersysteem.",
    year: 2026,
    image: heroImg,
    technologies: ["React", "TypeScript", "PHP", "MySQL"],
    status: "published",
  },
];
