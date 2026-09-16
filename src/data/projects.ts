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

    client: "Eigen project",
    role: "Webdesign & frontend development",
    challenge:
      "Een portfolio ontwikkelen waarin ik mijn werk professioneel kan presenteren en eenvoudig kan bijwerken.",
    solution:
      "Een React-website met herbruikbare componenten, met als volgende stap een eigen beheersysteem.",
    result:
      "Een portfolio in ontwikkeling met een homepage en een eerste projectensectie.",
    gallery: [heroImg],
  },
];
