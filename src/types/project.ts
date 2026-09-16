
export type Project = {
  id: number;
  title: string;
  description: string;
  year: number;
  image: string;
  technologies: string[];
  status: "draft" | "published";

  // Inhoud voor de casestudy
  client: string;
  role: string;
  challenge: string;
  solution: string;
  result: string;
  gallery: string[];
};