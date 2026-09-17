import { Navigate, Route, BrowserRouter as Router, Routes } from 'react-router-dom'
import { Nav } from './components/Nav'
import { Footer } from './components/Footer'
import { AuthProvider, useAuth } from './context/AuthContext'
import { BooksPage } from './pages/BooksPage'
import { NewBookPage } from './pages/NewBookPage'
import { BookDetailPage } from './pages/BookDetailPage'
import { DeclareTrouvaillePage } from './pages/DeclareTrouvaillePage'
import { ConfidentialitePage } from './pages/ConfidentialitePage'
import { LoginPage } from './pages/LoginPage'
import { RegisterPage } from './pages/RegisterPage'
import { ProfilePage } from './pages/ProfilePage'
import { ModerationPage } from './pages/ModerationPage'
import './App.css'

function RequireAuth({ children }) {
  const { isAuthenticated } = useAuth()
  return isAuthenticated ? children : <Navigate to="/connexion" replace />
}

// Back-office : accessible uniquement après chargement du rôle admin.
function RequireAdmin({ children }) {
  const { isAuthenticated, isAdmin, user } = useAuth()
  if (!isAuthenticated) return <Navigate to="/connexion" replace />
  if (user === null) return <div className="page"><p>Chargement…</p></div>
  if (!isAdmin) return <Navigate to="/" replace />
  return children
}

function AppRoutes() {
  return (
    <>
      <Nav />
      <main>
        <Routes>
          <Route path="/" element={<BooksPage />} />
          <Route path="/connexion" element={<LoginPage />} />
          <Route path="/inscription" element={<RegisterPage />} />
          <Route path="/confidentialite" element={<ConfidentialitePage />} />
          <Route path="/livres/:id" element={<BookDetailPage />} />
          <Route
            path="/livres/nouveau"
            element={
              <RequireAuth>
                <NewBookPage />
              </RequireAuth>
            }
          />
          <Route
            path="/trouvaille"
            element={
              <RequireAuth>
                <DeclareTrouvaillePage />
              </RequireAuth>
            }
          />
          <Route
            path="/profil"
            element={
              <RequireAuth>
                <ProfilePage />
              </RequireAuth>
            }
          />
          <Route
            path="/admin/signalements"
            element={
              <RequireAdmin>
                <ModerationPage />
              </RequireAdmin>
            }
          />
        </Routes>
      </main>
      <Footer />
    </>
  )
}

function App() {
  return (
    <AuthProvider>
      <Router>
        <AppRoutes />
      </Router>
    </AuthProvider>
  )
}

export default App
