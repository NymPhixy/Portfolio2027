export type Project = {
  id: number | string;
  title: string;
  description: string;
  year: number | null;
  image: string;
  imageIsPlaceholder?: boolean;
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
