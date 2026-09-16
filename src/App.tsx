import Navbar from "./components/Navbar";
import Hero from "./sections/Hero";

import "./App.css";

function App() {
  return (
    <>
      {/* Navigatie */}
      <Navbar />

      {/* Hoofdinhoud */}
      <main>
        <Hero />
      </main>
    </>
  );
}

export default App;
