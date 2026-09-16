import Navbar from "./components/Navbar";
import Hero from "./sections/Hero";
import Projects from "./sections/Projects";

import "./App.css";

function App() {
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
