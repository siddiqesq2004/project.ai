import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  Upload, FileText, Presentation, Code, Download, RefreshCw, 
  Send, CheckCircle, Sparkles, Zap, Layers, Eye, X, Bold, 
  Italic, Underline, List, ArrowRight, Brain, Target, Image as ImageIcon,
  ChevronRight, Plus, User, Lock, Mail, LogOut, ArrowLeft, Search, 
  Shield, LayoutDashboard, Database, Cpu, CreditCard, Activity,
  BookOpen, GitBranch
} from 'lucide-react';
import toast, { Toaster } from 'react-hot-toast';

const SimpleEditor = ({ value, onChange }) => {
  const handleCommand = (e, cmd) => {
    e.preventDefault();
    document.execCommand(cmd, false, null);
  };
  return (
    <div className="editor-container">
      <div className="editor-toolbar">
        <button className="editor-btn" type="button" onMouseDown={(e) => handleCommand(e, 'bold')} title="Bold"><Bold size={18} /></button>
        <button className="editor-btn" type="button" onMouseDown={(e) => handleCommand(e, 'italic')} title="Italic"><Italic size={18} /></button>
        <button className="editor-btn" type="button" onMouseDown={(e) => handleCommand(e, 'underline')} title="Underline"><Underline size={18} /></button>
        <div style={{ width: '1px', height: '20px', background: '#cbd5e1', margin: '0 4px' }}></div>
        <button className="editor-btn" type="button" onMouseDown={(e) => handleCommand(e, 'insertUnorderedList')} title="Bullet List"><List size={18} /></button>
        <button className="editor-btn" type="button" onMouseDown={(e) => handleCommand(e, 'insertOrderedList')} title="Numbered List"><span style={{fontWeight: '700', fontSize: '15px'}}>1.</span></button>
      </div>
      <div 
        contentEditable 
        dangerouslySetInnerHTML={{ __html: value }} 
        onBlur={(e) => onChange(e.target.innerHTML)}
        style={{ padding: '20px', minHeight: '150px', outline: 'none', color: '#334155', textAlign: 'justify', fontSize: '1.05rem', lineHeight: '1.7', fontFamily: 'system-ui, -apple-system, sans-serif' }}
      />
    </div>
  );
};

const API_BASE = '/api';

const ENDPOINTS = {
  auth: `${API_BASE}/auth.php`,
  projects: `${API_BASE}/projects.php`,
  getPhaseData: `${API_BASE}/get_phase_data.php`,
  generateOutline: `${API_BASE}/generate_outline.php`,
  generateChapter: `${API_BASE}/generate_chapter.php`,
  finalize: `${API_BASE}/finalize.php`
};

function App() {
  // Session States
  const [user, setUser] = useState(() => {
    const saved = localStorage.getItem('user_session');
    return saved ? JSON.parse(saved) : null;
  });
  const [isGenerating, setIsGenerating] = useState(false);
  const [projects, setProjects] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');

  // Auth Portal States
  const [authMode, setAuthMode] = useState('login'); // 'login' | 'signup'
  const [authForm, setAuthForm] = useState({ name: '', email: '', password: '', captchaInput: '' });
  const [captcha, setCaptcha] = useState({ question: '', answer: '' });

  const generateCaptcha = () => {
    const numA = Math.floor(Math.random() * 8) + 2; // 2 to 9
    const numB = Math.floor(Math.random() * 8) + 2; // 2 to 9
    setCaptcha({
      question: `What is ${numA} + ${numB}?`,
      answer: String(numA + numB)
    });
    setAuthForm(prev => ({ ...prev, captchaInput: '' }));
  };

  useEffect(() => {
    if (authMode === 'signup') {
      generateCaptcha();
    }
  }, [authMode]);

  // Generator States
  const [step, setStep] = useState(0); // 0: Setup, 1: Problem, 2: Methodology, 3: Results, 4: Review
  const [loading, setLoading] = useState(false);
  const [formData, setFormData] = useState({
    title: '',
    domain: '',
    requirements: '',
    reportTemplate: null,
    pptTemplate: null,
    problem: '',
    methodology: '',
    resultsText: '',
    resultImages: []
  });

  const [phaseData, setPhaseData] = useState({
    explanation: '',
    options: [],
    resultsText: '',
    suggestedFigures: []
  });

  const [progress, setProgress] = useState(0);
  const [loadingText, setLoadingText] = useState('');
  const [generatedContent, setGeneratedContent] = useState(null);
  const [showPreviewModal, setShowPreviewModal] = useState(false);
  const [activePreviewImage, setActivePreviewImage] = useState(null);

  const [usageStats, setUsageStats] = useState({ total_input_tokens: 0, total_output_tokens: 0, total_cost_usd: 0.0, anthropic_funded_credits: 50.00, anthropic_remaining_credits: 50.00, logs: [], students: [], student_limits: {} });

  // Load Projects on login
  useEffect(() => {
    if (user) {
      loadProjects();
    }
  }, [user]);

  const loadProjects = async () => {
    if (!user) return;
    try {
      const res = await axios.get(ENDPOINTS.projects, {
        params: { action: 'list', email: user.email }
      });
      if (res.data.projects) {
        setProjects(res.data.projects);
      }

      // Fetch Claude usage stats if Admin!
      if (user.role === 'admin') {
        const statsRes = await axios.get(`${API_BASE}/usage_stats.php`, {
          params: { email: user.email }
        });
        if (statsRes.data.success) {
          setUsageStats({
            total_input_tokens: statsRes.data.total_input_tokens,
            total_output_tokens: statsRes.data.total_output_tokens,
            total_cost_usd: statsRes.data.total_cost_usd,
            anthropic_funded_credits: statsRes.data.anthropic_funded_credits,
            anthropic_remaining_credits: statsRes.data.anthropic_remaining_credits,
            student_limits: statsRes.data.student_limits || {},
            logs: statsRes.data.logs || [],
            students: statsRes.data.students || []
          });
        }
      }
    } catch (err) {
      toast.error('Failed to load project database.');
    }
  };

  const handleExtendCredits = async (studentEmail) => {
    try {
      const res = await axios.post(`${API_BASE}/extend_credits.php`, {
        admin_email: user.email,
        student_email: studentEmail,
        amount: 3.00
      });
      if (res.data.error) throw new Error(res.data.error);
      toast.success(res.data.message || 'Credits extended successfully!');
      loadProjects();
    } catch (err) {
      toast.error(err.message || 'Failed to extend credits.');
    }
  };

  // Auth Handler
  const handleAuthSubmit = async (e) => {
    e.preventDefault();
    if (!authForm.email || !authForm.password || (authMode === 'signup' && (!authForm.name || !authForm.captchaInput))) {
      toast.error('Please fill in all required fields.');
      return;
    }

    if (authMode === 'signup') {
      if (authForm.captchaInput.trim() !== captcha.answer) {
        toast.error('Incorrect CAPTCHA answer. Please try again!');
        generateCaptcha();
        return;
      }
    }

    setLoading(true);
    try {
      const res = await axios.post(ENDPOINTS.auth, {
        action: authMode === 'signup' ? 'signup' : 'login',
        name: authForm.name,
        email: authForm.email,
        password: authForm.password,
        captcha_answer: captcha.answer,
        captcha_input: authForm.captchaInput
      });
      if (res.data.error) throw new Error(res.data.error);
      
      const userData = res.data.user;
      setUser(userData);
      localStorage.setItem('user_session', JSON.stringify(userData));
      toast.success(authMode === 'login' ? `Welcome back, ${userData.name}!` : 'Account created successfully!');
      
      // Reset form
      setAuthForm({ name: '', email: '', password: '', captchaInput: '' });
      setIsGenerating(false);
    } catch (err) {
      toast.error(err.message || 'Authentication failed.');
      if (authMode === 'signup') generateCaptcha();
    } finally {
      setLoading(false);
    }
  };

  const handleLogout = () => {
    setUser(null);
    localStorage.removeItem('user_session');
    setIsGenerating(false);
    setProjects([]);
    toast.success('Logged out successfully.');
  };

  // Project Actions
  const handleCreateNewProject = () => {
    setFormData({
      title: '',
      domain: '',
      requirements: '',
      reportTemplate: null,
      pptTemplate: null,
      problem: '',
      methodology: '',
      resultsText: '',
      resultImages: []
    });
    setGeneratedContent(null);
    setStep(0);
    setIsGenerating(true);
  };

  const handleOpenProject = (proj) => {
    const payload = proj.payload;
    setFormData(payload.formData);
    setGeneratedContent(payload.generatedContent);
    setStep(payload.currentStep !== undefined ? payload.currentStep : 4);
    setIsGenerating(true);
    toast.success(`Resumed: ${proj.title}`);
  };

  const handleDeleteProject = async (id, e) => {
    e.stopPropagation();
    if (!window.confirm('Are you sure you want to delete this project?')) return;
    try {
      const res = await axios.post(ENDPOINTS.projects, {
        action: 'delete',
        email: user.email,
        id: id
      });
      if (res.data.error) throw new Error(res.data.error);
      toast.success('Project deleted successfully.');
      loadProjects();
    } catch (err) {
      toast.error('Failed to delete project.');
    }
  };

  const handleSaveChanges = async () => {
    if (!user) return;
    setLoading(true);
    try {
      const payload = {
        formData: {
          title: formData.title,
          domain: formData.domain,
          requirements: formData.requirements,
          problem: formData.problem,
          methodology: formData.methodology,
          resultsText: formData.resultsText,
          architectureDiagram: formData.architectureDiagram,
          resultImages: formData.resultImages.map(img => ({
            id: img.id,
            title: img.title,
            description: img.description || '',
            chartConfig: img.chartConfig,
            type: img.type
          }))
        },
        generatedContent: generatedContent,
        currentStep: step
      };
      
      const res = await axios.post(ENDPOINTS.projects, {
        action: 'save',
        email: user.email,
        title: formData.title,
        domain: formData.domain,
        payload: payload
      });
      if (res.data.error) throw new Error(res.data.error);
      toast.success('Project draft saved successfully!');
      loadProjects();
    } catch (err) {
      toast.error(`Save failed: ${err.message}`);
    } finally {
      setLoading(false);
    }
  };

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData({ ...formData, [name]: value });
  };

  const handleFileChange = (e) => {
    const { name, files } = e.target;
    if (name === 'resultImages') {
      const newImages = Array.from(files).map((file, index) => ({
        file,
        id: `upload-${Date.now()}-${index}`,
        title: `User Uploaded Figure: ${file.name}`,
        type: 'upload'
      }));
      setFormData({ ...formData, resultImages: [...formData.resultImages, ...newImages] });
    } else {
      setFormData({ ...formData, [name]: files[0] });
    }
  };

  const removeImage = (id) => {
    setFormData({ ...formData, resultImages: formData.resultImages.filter(img => img.id !== id) });
  };

  // Step Transitions
  const goToPhase1 = async (e) => {
    e.preventDefault();
    if (!formData.title || !formData.domain) {
      toast.error('Title and Domain are required!');
      return;
    }
    setLoading(true);
    setLoadingText('Consulting AI for problem identification...');
    try {
      const res = await axios.post(ENDPOINTS.getPhaseData, {
        title: formData.title,
        domain: formData.domain,
        phase: 1,
        email: user.email
      }, { timeout: 120000 });
      if (res.data.error) throw new Error(res.data.error);
      setPhaseData(res.data);
      setStep(1);
    } catch (err) {
      const msg = err.message?.includes('overloaded') || err.message?.includes('529')
        ? 'AI engine is temporarily overloaded. Please try again in a moment.'
        : err.message || 'Failed to connect to AI engine.';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  };

  const goToPhase2 = async () => {
    if (!formData.problem) {
      toast.error('Please select or enter a problem statement!');
      return;
    }
    setLoading(true);
    setLoadingText('Generating methodology options...');
    try {
      const res = await axios.post(ENDPOINTS.getPhaseData, {
        title: formData.title,
        domain: formData.domain,
        problem: formData.problem,
        phase: 2,
        email: user.email
      }, { timeout: 120000 });
      if (res.data.error) throw new Error(res.data.error);
      setPhaseData(res.data);
      setStep(2);
    } catch (err) {
      const msg = err.message?.includes('overloaded') || err.message?.includes('529')
        ? 'AI engine is temporarily overloaded. Please try again in a moment.'
        : err.message || 'Failed to load methodology data.';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  };

  const goToPhase3 = () => {
    if (!formData.methodology) {
      toast.error('Please select or enter a methodology!');
      return;
    }
    setStep(3);
  };

  const generateAIResults = async () => {
    setLoading(true);
    setLoadingText('Simulating execution & generating technical results...');
    try {
      const res = await axios.post(ENDPOINTS.getPhaseData, {
        title: formData.title,
        domain: formData.domain,
        problem: formData.problem,
        methodology: formData.methodology,
        phase: 3,
        email: user.email
      }, { timeout: 120000 });
      if (res.data.error) throw new Error(res.data.error);
      
      setFormData({
        ...formData,
        resultsText: res.data.resultsText,
        architectureDiagram: res.data.architectureDiagram,
        resultImages: [
          ...formData.resultImages,
          ...(res.data.suggestedFigures || []).map((fig, i) => ({
            id: `ai-${Date.now()}-${i}`,
            title: fig.title,
            description: fig.description,
            chartConfig: fig.chartConfig,
            type: 'ai'
          }))
        ]
      });
      toast.success('Results generated based on implementation simulation!');
    } catch (err) {
      const msg = err.message?.includes('overloaded') || err.message?.includes('529')
        ? 'AI engine is temporarily overloaded. Please try again in a moment.'
        : err.message || 'Failed to generate AI results.';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  };

  const handleGenerate = async () => {
    setLoading(true);
    setProgress(0);
    setLoadingText('Architecting Project Deliverables...');

    const data = new FormData();
    data.append('title', formData.title);
    data.append('domain', formData.domain);
    data.append('email', user.email);
    
    const figuresContext = formData.resultImages.map((img, i) => 
      `Figure ${i+1}: ${img.title} (${img.type === 'ai' ? 'AI Generated Description: ' + img.description : 'User Uploaded File: ' + img.file?.name})`
    ).join('\n');

    const fullRequirements = `
      SELECTED PROBLEM: ${formData.problem}
      SELECTED METHODOLOGY: ${formData.methodology}
      PROJECT RESULTS: ${formData.resultsText}
      LIST OF FIGURES:
      ${figuresContext}
      USER SPECIFICATIONS: ${formData.requirements}
    `;
    data.append('requirements', fullRequirements);
    if (formData.reportTemplate) data.append('reportTemplate', formData.reportTemplate);
    if (formData.pptTemplate) data.append('pptTemplate', formData.pptTemplate);

    // Note: We do NOT send actual image files to generate_outline.php
    // That endpoint only needs text data. The figure descriptions are
    // already included in fullRequirements via figuresContext.

    try {
      const outlineRes = await axios.post(ENDPOINTS.generateOutline, data);
      if (outlineRes.data.error) throw new Error(outlineRes.data.error);
      
      const outline = outlineRes.data;
      const totalChapters = outline.chapters.length;
      setLoadingText('Synthesizing technical chapters...');
      const allGeneratedSections = [];
      const batchSize = 1; 

      for (let i = 0; i < outline.chapters.length; i += batchSize) {
        const batch = outline.chapters.slice(i, i + batchSize);
        setLoadingText(`Synthesizing chapter ${i + 1} of ${totalChapters}...`);
        
        const batchPromises = batch.map(async (item) => {
          const chapterName = typeof item === 'object' ? (item.title || item.heading || 'Technical Chapter') : item;
          let retries = 2;
          let success = false;
          let lastRes = null;

          while (retries > 0 && !success) {
            try {
              const res = await axios.post(ENDPOINTS.generateChapter, {
                title: formData.title,
                domain: formData.domain,
                chapterName: chapterName,
                context: fullRequirements,
                email: user.email
              });
              if (res.data && res.data.content) {
                success = true;
                lastRes = res.data;
              }
            } catch (err) {
              retries--;
              if (retries > 0) await new Promise(r => setTimeout(r, 2000));
            }
          }

          if (success) {
            return {
              heading: lastRes.heading || chapterName,
              content: lastRes.content
            };
          } else {
            return { heading: chapterName, content: `<p><em>Generation failed after multiple attempts. Please click "Regenerate" or edit manually.</em></p>` };
          }
        });

        const batchResults = await Promise.all(batchPromises);
        allGeneratedSections.push(...batchResults);
        setProgress(Math.round(((i + batch.length) / totalChapters) * 100));
        await new Promise(r => setTimeout(r, 500));
      }

      outline.sections = allGeneratedSections;
      outline.architectureDiagram = formData.architectureDiagram;
      outline.figures = formData.resultImages.map((img, i) => ({
        id: i + 1,
        title: img.title,
        description: img.description || '',
        chartConfig: img.chartConfig,
        type: img.type
      }));
      
      setGeneratedContent(outline);
      setStep(4);
      toast.success('Full project generated successfully!', { icon: '🏆' });

      // Automatically save to database upon creation
      const payload = {
        formData: {
          title: formData.title,
          domain: formData.domain,
          requirements: formData.requirements,
          problem: formData.problem,
          methodology: formData.methodology,
          resultsText: formData.resultsText,
          architectureDiagram: formData.architectureDiagram,
          resultImages: formData.resultImages.map(img => ({
            id: img.id,
            title: img.title,
            description: img.description || '',
            chartConfig: img.chartConfig,
            type: img.type
          }))
        },
        generatedContent: outline
      };
      
      await axios.post(ENDPOINTS.projects, {
        action: 'save',
        email: user.email,
        title: formData.title,
        domain: formData.domain,
        payload: payload
      });
      loadProjects();

    } catch (error) {
      toast.error(`Generation Failed: ${error.message}`);
    } finally {
      setLoading(false);
    }
  };

  const handleFinalize = async () => {
    setLoading(true);
    try {
      const response = await axios.post(ENDPOINTS.finalize, generatedContent, { responseType: 'blob' });
      
      // If the response is a JSON error rather than a ZIP file
      if (response.data.type === 'application/json') {
        const text = await response.data.text();
        try {
          const errorData = JSON.parse(text);
          toast.error(errorData.error || 'Export failed.');
        } catch (e) {
          toast.error('Export failed: Server returned an invalid response.');
        }
        return;
      }

      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `${formData.title.replace(/\s+/g, '_')}_Final_Report.zip`);
      document.body.appendChild(link);
      link.click();
      toast.success('Final ZIP downloaded successfully!');
    } catch (error) {
      toast.error('Export failed: Connection error or server is offline.');
    } finally {
      setLoading(false);
    }
  };

  // Content Update Helpers
  const updateAbstract = (val) => setGeneratedContent({...generatedContent, abstract: val});
  const updateSectionHeading = (index, val) => {
    const newSections = [...generatedContent.sections];
    newSections[index].heading = val;
    setGeneratedContent({...generatedContent, sections: newSections});
  };
  const updateSectionContent = (index, val) => {
    const newSections = [...generatedContent.sections];
    newSections[index].content = val;
    setGeneratedContent({...generatedContent, sections: newSections});
  };
  const updateSlideTitle = (i, val) => {
    const newPpt = [...generatedContent.ppt];
    newPpt[i].title = val;
    setGeneratedContent({...generatedContent, ppt: newPpt});
  };
  const updateSlideSubtitle = (i, val) => {
    const newPpt = [...generatedContent.ppt];
    newPpt[i].subtitle = val;
    setGeneratedContent({...generatedContent, ppt: newPpt});
  };
  const updateSlidePoint = (i, j, val) => {
    const newPpt = [...generatedContent.ppt];
    newPpt[i].points[j] = val;
    setGeneratedContent({...generatedContent, ppt: newPpt});
  };
  const updateCodeFile = (i, val) => {
    const newCodeFiles = [...generatedContent.code.files];
    newCodeFiles[i].content = val;
    setGeneratedContent({...generatedContent, code: { ...generatedContent.code, files: newCodeFiles }});
  };
  const addNewChapter = () => {
    setGeneratedContent({
      ...generatedContent,
      sections: [...generatedContent.sections, { heading: 'New Chapter', content: '<p>Content here...</p>' }]
    });
  };

  const containerVariants = {
    hidden: { opacity: 0, x: 20 },
    visible: { opacity: 1, x: 0, transition: { duration: 0.5 } },
    exit: { opacity: 0, x: -20, transition: { duration: 0.3 } }
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 10 },
    visible: { opacity: 1, y: 0 }
  };

  const StepIndicator = () => {
    const phaseNames = ["Project Setup", "Problem ID", "Methodology", "Result Graphs", "Review & Doc"];
    return (
      <div className="step-indicator-container">
        <div className="step-indicator">
          {[0, 1, 2, 3, 4].map((s) => (
            <React.Fragment key={s}>
              <div 
                className="step-dot-wrapper"
                onClick={() => { if (s <= step) { setLoading(false); setStep(s); } }}
                style={{ cursor: s <= step ? 'pointer' : 'not-allowed' }}
                title={phaseNames[s]}
              >
                <div className={`step-dot ${step >= s ? 'active' : ''} ${step === s ? 'current' : ''} ${s <= step ? 'clickable' : ''}`}>
                  {step > s ? <CheckCircle size={14} /> : s + 1}
                </div>
                <span className={`step-label ${step >= s ? 'active' : ''} ${step === s ? 'current' : ''}`}>
                  {phaseNames[s]}
                </span>
              </div>
              {s < 4 && <div className={`step-line ${step > s ? 'active' : ''}`}></div>}
            </React.Fragment>
          ))}
        </div>
      </div>
    );
  };

  // Filtered project list
  const filteredProjects = projects.filter(p => 
    p.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
    p.domain.toLowerCase().includes(searchQuery.toLowerCase()) ||
    (p.email && p.email.toLowerCase().includes(searchQuery.toLowerCase()))
  );

  // Filtered student list for Admin Dashboard
  const filteredStudents = (usageStats.students || []).filter(student => 
    student.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
    student.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    student.active_phase.toLowerCase().includes(searchQuery.toLowerCase()) ||
    student.latest_project_title.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <>
      {/* Dynamic Animated Particles Background */}
      <div className="background-elements">
        <div className="orb orb-1"></div>
        <div className="orb orb-2"></div>
        <div className="orb orb-3"></div>
        <div className="orb orb-4"></div>
      </div>
      
      <div className="container">
        <Toaster position="top-right" />
        
        {/* --- SCENE 1: PORTAL LOGIN / SIGNUP --- */}
        {!user && (
          <div className="auth-portal-wrapper">
            {/* Left Content Column */}
            <div className="auth-left-content">
              <div className="auth-brand-logo">
                <Sparkles size={24} color="#ff2a5f" className="sparkle-pulse" style={{ animation: 'float 6s infinite ease-in-out' }} />
                <h3>Project AI</h3>
              </div>

              <h1 className="auth-hero-title">
                Accelerate your Research with <span className="gradient-text">AI at your side</span>
              </h1>

              <p className="auth-hero-subtitle">
                Draft comprehensive academic chapters, structure performance simulations, build vector flowcharts, and compile presentation outlines — everything a researcher needs in one seamless interface.
              </p>

              <div className="landing-features-list">
                <div className="landing-feature-item">
                  <div className="feature-icon-box f1">
                    <BookOpen size={20} />
                  </div>
                  <div className="feature-text">
                    <h4>AI-Generated Research Chapters</h4>
                    <p>Deep, publication-grade academic chapters complete with rigorous theory.</p>
                  </div>
                </div>

                <div className="landing-feature-item">
                  <div className="feature-icon-box f2">
                    <Activity size={20} />
                  </div>
                  <div className="feature-text">
                    <h4>Real-time Performance Simulation</h4>
                    <p>Generate simulated performance charts and sandbox execution logs instantly.</p>
                  </div>
                </div>

                <div className="landing-feature-item">
                  <div className="feature-icon-box f3">
                    <GitBranch size={20} />
                  </div>
                  <div className="feature-text">
                    <h4>Dynamic Vector Flowcharts</h4>
                    <p>Configure editable vector block diagrams and polished slide structures.</p>
                  </div>
                </div>
              </div>
            </div>

            {/* Right Content Column (Form Card) */}
            <motion.div 
              initial={{ opacity: 0, y: 30 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6 }}
              className="glass-card auth-card"
            >
              <div className="auth-header-logo">
                <div className="logo-sparkle-box">
                  <Sparkles size={36} color="#ff2a5f" className="sparkle-pulse" />
                </div>
                <h2>Project AI</h2>
                <p>Secure Institutional Portal</p>
              </div>

              {/* Custom Portal Toggle */}
              <div className="auth-toggle-bar">
                <button 
                  className={`toggle-btn ${authMode === 'login' ? 'active' : ''}`}
                  onClick={() => setAuthMode('login')}
                >
                  <User size={16} /> Sign In
                </button>
                <button 
                  className={`toggle-btn ${authMode === 'signup' ? 'active' : ''}`}
                  onClick={() => setAuthMode('signup')}
                >
                  <Plus size={16} /> Student Register
                </button>
              </div>

              <form onSubmit={handleAuthSubmit} style={{ marginTop: '2rem' }}>
                {authMode === 'signup' && (
                  <div className="input-group">
                    <label>Full Name</label>
                    <div className="auth-input-wrapper">
                      <User size={18} className="auth-icon" />
                      <input 
                        type="text" 
                        placeholder="Enter your name" 
                        value={authForm.name} 
                        onChange={(e) => setAuthForm({ ...authForm, name: e.target.value })}
                        required
                      />
                    </div>
                  </div>
                )}

                <div className="input-group">
                  <label>Institutional Email</label>
                  <div className="auth-input-wrapper">
                    <Mail size={18} className="auth-icon" />
                    <input 
                      type="email" 
                      placeholder="e.g. yourname@gmail.com" 
                      value={authForm.email} 
                      onChange={(e) => setAuthForm({ ...authForm, email: e.target.value })}
                      required
                    />
                  </div>
                </div>

                <div className="input-group">
                  <label>Access Password</label>
                  <div className="auth-input-wrapper">
                    <Lock size={18} className="auth-icon" />
                    <input 
                      type="password" 
                      placeholder="••••••••" 
                      value={authForm.password} 
                      onChange={(e) => setAuthForm({ ...authForm, password: e.target.value })}
                      required
                    />
                  </div>
                </div>

                {authMode === 'signup' && (
                  <div className="input-group" style={{ animation: 'fadeIn 0.3s ease' }}>
                    <label style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', color: '#fff', fontSize: '0.9rem' }}>
                      <span>Security CAPTCHA: <strong style={{ color: '#00f2fe' }}>{captcha.question}</strong></span>
                      <button 
                        type="button" 
                        onClick={generateCaptcha} 
                        style={{ background: 'none', border: 'none', color: '#00f2fe', cursor: 'pointer', fontSize: '0.75rem', textDecoration: 'underline', padding: 0 }}
                      >
                        Refresh CAPTCHA
                      </button>
                    </label>
                    <div className="auth-input-wrapper">
                      <Shield size={18} className="auth-icon" style={{ color: '#00f2fe' }} />
                      <input 
                        type="text" 
                        placeholder="Solve the math sum..." 
                        value={authForm.captchaInput} 
                        onChange={(e) => setAuthForm({ ...authForm, captchaInput: e.target.value })}
                        required
                      />
                    </div>
                  </div>
                )}
 
                <button type="submit" className="btn-primary auth-submit-btn" disabled={loading}>
                  {loading ? <RefreshCw className="animate-spin" /> : (
                    authMode === 'login' ? <><Zap size={18} /> Enter Platform</> : <><Sparkles size={18} /> Create Account</>
                  )}
                </button>

                <div className="auth-footer-tag">
                  <Shield size={12} /> Secure Academic Credentials Portal
                </div>
              </form>
            </motion.div>
          </div>
        )}

        {/* --- SCENE 2: STUDENT DASHBOARD --- */}
        {user && user.role === 'student' && !isGenerating && (
          <motion.div 
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="dashboard-wrapper"
          >
            {/* Header Dashboard Banner */}
            <div className="dashboard-header">
              <div className="db-welcome">
                <h2>Welcome back, {user.name}!</h2>
                <p>Manage and generate your premium research papers & artifacts</p>
              </div>
              <div className="db-actions">
                <button onClick={handleLogout} className="btn-secondary logout-btn">
                  <LogOut size={16} /> Sign Out
                </button>
              </div>
            </div>

            {/* Quick Stats Grid */}
            <div className="stats-grid">
              <div className="stat-card">
                <div className="stat-icon"><LayoutDashboard size={20} /></div>
                <div className="stat-info">
                  <h3>{projects.length}</h3>
                  <p>My Saved Projects</p>
                </div>
              </div>
              <div className="stat-card accent">
                <div className="stat-icon"><Sparkles size={20} /></div>
                <div className="stat-info">
                  <h3>Active</h3>
                  <p>Rademics AI Workspace</p>
                </div>
              </div>
            </div>

            {/* Research Journey Workflow (4 Copilot Phases) */}
            <div className="glass-card journey-workflow-card">
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '1rem' }}>
                <Brain size={20} color="#00f2fe" />
                <h4 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 600, color: '#fff' }}>Academic Research Copilot Workflow</h4>
              </div>
              <p style={{ margin: '0 0 1.5rem 0', fontSize: '0.85rem', color: 'rgba(255,255,255,0.6)' }}>Your research proposal is structured and simulated step-by-step through four comprehensive phases:</p>
              
              <div className="journey-grid">
                <div className="phase-card p1">
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                    <span style={{ fontSize: '0.75rem', fontWeight: 700, textTransform: 'uppercase', color: '#ff2a5f', padding: '0.25rem 0.5rem', borderRadius: '4px', background: 'rgba(255, 42, 95, 0.1)' }}>Phase 1</span>
                    <Target size={16} style={{ color: '#ff2a5f' }} />
                  </div>
                  <h5 style={{ margin: '0 0 0.4rem 0', fontSize: '0.9rem', fontWeight: 600, color: '#fff' }}>Problem Formulation</h5>
                  <p style={{ margin: 0, fontSize: '0.8rem', color: 'rgba(255,255,255,0.5)', lineHeight: '1.4' }}>Pinpoint specific domain gaps and define clean, standard technical problem statements.</p>
                </div>

                <div className="phase-card p2">
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                    <span style={{ fontSize: '0.75rem', fontWeight: 700, textTransform: 'uppercase', color: '#00f2fe', padding: '0.25rem 0.5rem', borderRadius: '4px', background: 'rgba(0, 242, 254, 0.1)' }}>Phase 2</span>
                    <Layers size={16} style={{ color: '#00f2fe' }} />
                  </div>
                  <h5 style={{ margin: '0 0 0.4rem 0', fontSize: '0.9rem', fontWeight: 600, color: '#fff' }}>Methodology Strategy</h5>
                  <p style={{ margin: 0, fontSize: '0.8rem', color: 'rgba(255,255,255,0.5)', lineHeight: '1.4' }}>Structure mathematical algorithms, system architectures, and research workflows.</p>
                </div>

                <div className="phase-card p3">
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                    <span style={{ fontSize: '0.75rem', fontWeight: 700, textTransform: 'uppercase', color: '#10b981', padding: '0.25rem 0.5rem', borderRadius: '4px', background: 'rgba(16, 185, 129, 0.1)' }}>Phase 3</span>
                    <Zap size={16} style={{ color: '#10b981' }} />
                  </div>
                  <h5 style={{ margin: '0 0 0.4rem 0', fontSize: '0.9rem', fontWeight: 600, color: '#fff' }}>Simulation & Graphing</h5>
                  <p style={{ margin: 0, fontSize: '0.8rem', color: 'rgba(255,255,255,0.5)', lineHeight: '1.4' }}>Simulate execution metrics, build vector flowcharts, and configure performance charts.</p>
                </div>

                <div className="phase-card p4">
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '0.5rem' }}>
                    <span style={{ fontSize: '0.75rem', fontWeight: 700, textTransform: 'uppercase', color: '#a855f7', padding: '0.25rem 0.5rem', borderRadius: '4px', background: 'rgba(168, 85, 247, 0.1)' }}>Phase 4</span>
                    <Sparkles size={16} style={{ color: '#a855f7' }} />
                  </div>
                  <h5 style={{ margin: '0 0 0.4rem 0', fontSize: '0.9rem', fontWeight: 600, color: '#fff' }}>Synthesis & Review</h5>
                  <p style={{ margin: 0, fontSize: '0.8rem', color: 'rgba(255,255,255,0.5)', lineHeight: '1.4' }}>Draft comprehensive technical chapters, PPT slides, code, and download ZIP report.</p>
                </div>
              </div>
            </div>

            {/* Project List Search & Title */}
            <div className="project-list-controls">
              <h3>My Research Projects</h3>
              <div className="search-bar-wrapper">
                <Search size={16} />
                <input 
                  type="text" 
                  placeholder="Search projects by title or domain..." 
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>
            </div>

            {/* Main Dashboard Panel */}
            <div className="dashboard-grid">
              {/* Box 1: Create New Project CTA */}
              <div className="project-card create-cta-card" onClick={handleCreateNewProject}>
                <div className="create-cta-sparkle">
                  <Plus size={36} color="#00d2ff" />
                </div>
                <h4>Generate New Research Project</h4>
                <p>Identify research problems, select methodologies, run simulations, and download ready-to-publish papers & PPTs.</p>
              </div>

              {/* User Saved Projects */}
              {filteredProjects.map((proj) => (
                <div key={proj.id} className="project-card" onClick={() => handleOpenProject(proj)}>
                  <div className="proj-card-icon"><FileText size={22} /></div>
                  <div className="proj-card-content">
                    <h4>{proj.title}</h4>
                    <span className="proj-domain">{proj.domain}</span>
                    <div className="proj-meta">
                      <span>Saved: {new Date(proj.updated_at || proj.created_at).toLocaleDateString()}</span>
                    </div>
                  </div>
                  <div className="proj-card-hover-actions">
                    <button 
                      onClick={(e) => handleDeleteProject(proj.id, e)} 
                      className="btn-delete"
                      title="Delete Project"
                    >
                      <X size={16} />
                    </button>
                    <button className="btn-resume">
                      Resume <ArrowRight size={14} />
                    </button>
                  </div>
                </div>
              ))}
            </div>

            {filteredProjects.length === 0 && searchQuery !== '' && (
              <div className="empty-search-state">
                <ImageIcon size={48} />
                <p>No projects match your search query.</p>
              </div>
            )}
          </motion.div>
        )}

        {/* --- SCENE 3: ADMIN DASHBOARD --- */}
        {user && user.role === 'admin' && !isGenerating && (
          <motion.div 
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="dashboard-wrapper"
          >
            {/* Header Dashboard Banner */}
            <div className="dashboard-header">
              <div className="db-welcome">
                <h2 style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <Shield size={24} color="#ff2a5f" /> Admin Portal Dashboard
                </h2>
                <p>Monitor generated projects and download research reports across all students</p>
              </div>
              <div className="db-actions">
                <button onClick={handleLogout} className="btn-secondary logout-btn">
                  <LogOut size={16} /> Admin Log Out
                </button>
              </div>
            </div>

            {/* Quick Stats Grid */}
            <div className="stats-grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1.5rem' }}>
              <div className="stat-card">
                <div className="stat-icon"><User size={20} /></div>
                <div className="stat-info">
                  <h3>{Array.from(new Set(projects.map(p => p.email))).length}</h3>
                  <p>Registered Students</p>
                </div>
              </div>
              <div className="stat-card accent">
                <div className="stat-icon"><Database size={20} /></div>
                <div className="stat-info">
                  <h3>{projects.length}</h3>
                  <p>Total Generated Projects</p>
                </div>
              </div>
              <div className="stat-card" style={{ background: 'rgba(15, 23, 42, 0.45)', borderLeft: '4px solid #00f2fe' }}>
                <div className="stat-icon" style={{ background: 'rgba(0, 242, 254, 0.15)', color: '#00f2fe' }}><Cpu size={20} /></div>
                <div className="stat-info">
                  <h3>${parseFloat(usageStats.total_cost_usd || 0).toFixed(4)}</h3>
                  <p>Claude API spent (USD)</p>
                </div>
              </div>
            </div>

            {/* Claude API Credit & Billing Monitor Alert */}
            <div className="glass-card billing-alert-card" style={{ marginTop: '1.5rem', background: 'rgba(15, 23, 42, 0.45)', borderLeft: `6px solid ${(usageStats.anthropic_remaining_credits || 0) > 15.0 ? '#10b981' : (usageStats.anthropic_remaining_credits || 0) > 5.0 ? '#f59e0b' : '#ef4444'}`, borderTop: '1px solid rgba(255,255,255,0.08)', borderRight: '1px solid rgba(255,255,255,0.08)', borderBottom: '1px solid rgba(255,255,255,0.08)', borderRadius: '12px', overflow: 'hidden' }}>
              <div className="billing-content-layout" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1.5rem', padding: '1.5rem' }}>
                <div style={{ flex: '1', minWidth: '280px' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '0.5rem' }}>
                    <Cpu size={22} className="sparkle-pulse" style={{ color: '#00f2fe' }} />
                    <h4 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 600, color: '#fff' }}>Claude 3.5 Sonnet Engine Billing Monitor</h4>
                  </div>
                  <p style={{ margin: '0 0 1rem 0', fontSize: '0.9rem', color: 'rgba(255,255,255,0.7)', lineHeight: '1.5' }}>
                    Estimated Remaining Credits: <strong style={{ color: '#00f2fe', fontSize: '1.05rem' }}>${parseFloat(usageStats.anthropic_remaining_credits !== undefined ? usageStats.anthropic_remaining_credits : 50.00).toFixed(2)} USD</strong> (out of ${parseFloat(usageStats.anthropic_funded_credits !== undefined ? usageStats.anthropic_funded_credits : 50.00).toFixed(2)} logged).<br/>
                    Cumulative Spent: <strong style={{ color: '#fff' }}>${parseFloat(usageStats.total_cost_usd || 0).toFixed(4)} USD</strong>. 
                    Tokens Consumed: <span style={{ color: '#a5f3fc' }}>{((usageStats.total_input_tokens || 0) / 1000).toFixed(1)}k Input / {((usageStats.total_output_tokens || 0) / 1000).toFixed(1)}k Output</span>.
                  </p>
                  
                  {/* Glowing Status Message */}
                  <div style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem', padding: '0.4rem 0.8rem', borderRadius: '20px', fontSize: '0.8rem', fontWeight: 600, background: (usageStats.anthropic_remaining_credits || 0) > 15.0 ? 'rgba(16,185,129,0.15)' : (usageStats.anthropic_remaining_credits || 0) > 5.0 ? 'rgba(245,158,11,0.15)' : 'rgba(239,68,68,0.15)', color: (usageStats.anthropic_remaining_credits || 0) > 15.0 ? '#10b981' : (usageStats.anthropic_remaining_credits || 0) > 5.0 ? '#f59e0b' : '#ef4444', border: `1px solid ${(usageStats.anthropic_remaining_credits || 0) > 15.0 ? 'rgba(16,185,129,0.3)' : (usageStats.anthropic_remaining_credits || 0) > 5.0 ? 'rgba(245,158,11,0.3)' : 'rgba(239,68,68,0.3)'}` }}>
                    <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: (usageStats.anthropic_remaining_credits || 0) > 15.0 ? '#10b981' : (usageStats.anthropic_remaining_credits || 0) > 5.0 ? '#f59e0b' : '#ef4444', display: 'inline-block', boxShadow: `0 0 8px ${(usageStats.anthropic_remaining_credits || 0) > 15.0 ? '#10b981' : (usageStats.anthropic_remaining_credits || 0) > 5.0 ? '#f59e0b' : '#ef4444'}` }}></span>
                    {(usageStats.anthropic_remaining_credits || 0) > 15.0 
                      ? `🟢 Prepaid Credits Balance: HEALTHY ($${parseFloat(usageStats.anthropic_remaining_credits || 0).toFixed(2)} remaining)` 
                      : (usageStats.anthropic_remaining_credits || 0) > 5.0 
                        ? `⚠️ Prepaid Credits Balance: LOW BALANCE ($${parseFloat(usageStats.anthropic_remaining_credits || 0).toFixed(2)} remaining. recharge recommended)` 
                        : `🚨 Prepaid Credits Balance: DANGER ZONE ($${parseFloat(usageStats.anthropic_remaining_credits || 0).toFixed(2)} remaining. recharge now!)`}
                  </div>
                </div>

                <div className="billing-action-btn" style={{ display: 'flex', flexDirection: 'column', gap: '0.65rem', alignItems: 'flex-end' }}>
                  <a 
                    href="https://console.anthropic.com/settings/billing" 
                    target="_blank" 
                    rel="noreferrer" 
                    className="btn-primary" 
                    style={{ background: 'linear-gradient(135deg, #00f2fe 0%, #4facfe 100%)', border: 'none', display: 'flex', alignItems: 'center', gap: '0.5rem', padding: '0.75rem 1.25rem', borderRadius: '8px', fontWeight: 600, color: '#fff', textDecoration: 'none', boxShadow: '0 4px 15px rgba(0, 242, 254, 0.3)' }}
                  >
                    <CreditCard size={18} /> Recharge Claude Credits
                  </a>
                  <button 
                    onClick={async () => {
                      const amount = prompt("Enter the dollar amount ($ USD) you funded/topped-up on the Anthropic Console (e.g., 20.00 or 50.00):");
                      if (amount && !isNaN(amount)) {
                        try {
                          const res = await axios.post(`${API_BASE}/extend_credits.php`, {
                            admin_email: user.email,
                            student_email: 'anthropic_billing',
                            amount: parseFloat(amount)
                          });
                          if (res.data.success) {
                            toast.success(res.data.message);
                            loadProjects();
                          } else {
                            toast.error(res.data.error || "Failed to update balance.");
                          }
                        } catch (err) {
                          toast.error("Connection error: " + err.message);
                        }
                      }
                    }}
                    style={{ background: 'rgba(255,255,255,0.06)', border: '1px solid rgba(255,255,255,0.12)', display: 'inline-flex', alignItems: 'center', gap: '0.35rem', padding: '0.45rem 0.9rem', borderRadius: '6px', fontWeight: 600, color: '#fff', fontSize: '0.8rem', cursor: 'pointer', transition: 'all 0.2s' }}
                  >
                    <Plus size={14} /> Log Manual Top-up
                  </button>
                  <span style={{ fontSize: '0.75rem', color: 'rgba(255,255,255,0.4)' }}>Secured via official Anthropic Billing Console</span>
                </div>
              </div>
            </div>

            {/* Student Accounts and Research Progress Section */}
            <div className="project-list-controls" style={{ marginTop: '2.5rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <User size={20} color="#00f2fe" />
                <h3 style={{ margin: 0 }}>Registered Student Accounts & Research Progress</h3>
              </div>
              <p style={{ margin: 0, fontSize: '0.85rem', color: 'rgba(255,255,255,0.5)' }}>Real-time student registry showing active phase tracking and spent credits</p>
            </div>

            <div className="glass-card admin-table-card" style={{ marginTop: '1rem' }}>
              <table className="admin-projects-table">
                <thead>
                  <tr>
                    <th>Student Profile</th>
                    <th>Date Registered</th>
                    <th>Active Research Project</th>
                    <th>Active Phase Progress</th>
                    <th style={{ textAlign: 'center' }}>Spent / Budget Limit</th>
                    <th style={{ textAlign: 'center' }}>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredStudents.map((student) => (
                    <tr key={student.email} style={{ cursor: 'default' }}>
                      <td className="admin-td-email">
                        <div style={{ fontWeight: 700, color: '#fff' }}>{student.name}</div>
                        <div style={{ fontSize: '0.8rem', color: 'rgba(255, 255, 255, 0.55)' }}>{student.email}</div>
                      </td>
                      <td className="admin-td-date">
                        {student.created_at ? new Date(student.created_at).toLocaleDateString() : 'N/A'}
                      </td>
                      <td style={{ color: 'white', fontWeight: 500, maxWidth: '200px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {student.latest_project_title}
                      </td>
                      <td>
                        <span style={{ 
                          background: student.active_phase === 'Not Started' ? 'rgba(255,255,255,0.05)' :
                                      student.active_phase.includes('Completed') ? 'rgba(16, 185, 129, 0.15)' :
                                      student.active_phase.includes('Phase 3') || student.active_phase.includes('Phase 4') ? 'rgba(0, 242, 254, 0.15)' : 'rgba(255, 42, 95, 0.15)',
                          border: `1px solid ${
                                      student.active_phase === 'Not Started' ? 'rgba(255,255,255,0.1)' :
                                      student.active_phase.includes('Completed') ? 'rgba(16, 185, 129, 0.3)' :
                                      student.active_phase.includes('Phase 3') || student.active_phase.includes('Phase 4') ? 'rgba(0, 242, 254, 0.3)' : 'rgba(255, 42, 95, 0.3)'
                          }`,
                          color:      student.active_phase === 'Not Started' ? 'rgba(255,255,255,0.6)' :
                                      student.active_phase.includes('Completed') ? '#10b981' :
                                      student.active_phase.includes('Phase 3') || student.active_phase.includes('Phase 4') ? '#00f2fe' : '#ff2a5f',
                          padding: '4px 10px',
                          borderRadius: '20px',
                          fontWeight: 700,
                          fontSize: '0.75rem',
                          display: 'inline-flex',
                          alignItems: 'center',
                          gap: '4px'
                        }}>
                          <span style={{ 
                            width: '6px', 
                            height: '6px', 
                            borderRadius: '50%', 
                            background: student.active_phase === 'Not Started' ? 'rgba(255,255,255,0.4)' :
                                        student.active_phase.includes('Completed') ? '#10b981' :
                                        student.active_phase.includes('Phase 3') || student.active_phase.includes('Phase 4') ? '#00f2fe' : '#ff2a5f',
                            display: 'inline-block' 
                          }}></span>
                          {student.active_phase}
                        </span>
                      </td>
                      <td style={{ textAlign: 'center' }}>
                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                          <span style={{ color: '#10b981', fontWeight: 700 }}>
                            ${student.total_spend.toFixed(4)}
                          </span>
                          <span style={{ fontSize: '0.75rem', color: 'rgba(255, 255, 255, 0.4)' }}>
                            Limit: ${(student.credit_limit || 3.00).toFixed(2)}
                          </span>
                        </div>
                      </td>
                      <td style={{ textAlign: 'center' }}>
                        <button 
                          onClick={() => handleExtendCredits(student.email)} 
                          style={{ 
                            background: 'rgba(0, 242, 254, 0.12)', 
                            border: '1px solid rgba(0, 242, 254, 0.3)', 
                            color: '#00f2fe', 
                            cursor: 'pointer', 
                            fontSize: '0.8rem', 
                            padding: '6px 12px', 
                            borderRadius: '8px', 
                            display: 'inline-flex', 
                            alignItems: 'center', 
                            gap: '4px',
                            fontWeight: 600,
                            transition: 'all 0.2s'
                          }}
                          title="Add +$3.00 API Credits to this Student"
                        >
                          <Plus size={12} /> Add +$3 Limit
                        </button>
                      </td>
                    </tr>
                  ))}
                  {filteredStudents.length === 0 && (
                    <tr>
                      <td colSpan="6" style={{ textAlign: 'center', padding: '3rem', color: '#94a3b8' }}>
                        No registered student accounts found.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            {/* Admin Table Controls */}
            <div className="project-list-controls" style={{ marginTop: '2.5rem' }}>
              <h3>All Generated Student Deliverables</h3>
              <div className="search-bar-wrapper">
                <Search size={16} />
                <input 
                  type="text" 
                  placeholder="Search by student email, title, or domain..." 
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>
            </div>

            {/* Admin Table Panel */}
            <div className="glass-card admin-table-card">
              <table className="admin-projects-table">
                <thead>
                  <tr>
                    <th>Student Email</th>
                    <th>Research Project Title</th>
                    <th>Academic Domain</th>
                    <th>Date Created</th>
                    <th style={{ textAlign: 'center' }}>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredProjects.map((proj) => (
                    <tr key={proj.id} onClick={() => handleOpenProject(proj)}>
                      <td className="admin-td-email">
                        <div style={{ fontWeight: 600 }}>{proj.email}</div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.4rem', marginTop: '0.25rem', fontSize: '0.75rem' }}>
                          <span style={{ color: 'rgba(255,255,255,0.45)' }}>Spent:</span>
                          <span style={{ color: '#10b981', fontWeight: 600 }}>
                            ${usageStats.logs.filter(log => log.email.toLowerCase() === proj.email.toLowerCase()).reduce((sum, log) => sum + parseFloat(log.cost || 0), 0).toFixed(4)}
                          </span>
                          <span style={{ color: 'rgba(255,255,255,0.2)' }}>/</span>
                          <span style={{ color: 'rgba(255,255,255,0.45)' }}>Limit:</span>
                          <span style={{ color: '#00f2fe', fontWeight: 600 }}>
                            ${(usageStats.student_limits[proj.email.toLowerCase()] !== undefined ? usageStats.student_limits[proj.email.toLowerCase()] : 3.00).toFixed(2)}
                          </span>
                          <button 
                            onClick={(e) => { e.stopPropagation(); handleExtendCredits(proj.email); }} 
                            style={{ marginLeft: '0.4rem', background: 'rgba(0, 242, 254, 0.12)', border: '1px solid rgba(0, 242, 254, 0.3)', color: '#00f2fe', cursor: 'pointer', fontSize: '0.7rem', padding: '0.1rem 0.3rem', borderRadius: '4px', display: 'flex', alignItems: 'center', gap: '2px' }}
                            title="Add +$3.00 API Credits to this Student"
                          >
                            <Plus size={10} /> +$3
                          </button>
                        </div>
                      </td>
                      <td className="admin-td-title">{proj.title}</td>
                      <td className="admin-td-domain"><span className="admin-domain-badge">{proj.domain}</span></td>
                      <td className="admin-td-date">{new Date(proj.created_at).toLocaleDateString()}</td>
                      <td style={{ textAlign: 'center' }} onClick={(e) => e.stopPropagation()}>
                        <div style={{ display: 'flex', justifyContent: 'center', gap: '0.5rem' }}>
                          <button 
                            onClick={() => handleOpenProject(proj)} 
                            className="admin-action-btn"
                            title="Open & Download Deliverables"
                          >
                            <Eye size={14} /> Open
                          </button>
                          <button 
                            onClick={(e) => handleDeleteProject(proj.id, e)} 
                            className="admin-action-btn delete"
                            title="Remove Project Record"
                          >
                            <X size={14} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                  {filteredProjects.length === 0 && (
                    <tr>
                      <td colSpan="5" style={{ textAlign: 'center', padding: '3rem', color: '#94a3b8' }}>
                        No generated projects in database.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            {/* Claude API Usage Audit Trail Section */}
            <div className="project-list-controls" style={{ marginTop: '3rem' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <Activity size={20} color="#00f2fe" />
                <h3 style={{ margin: 0 }}>Claude 3.5 Sonnet Live Activity Trail</h3>
              </div>
              <p style={{ margin: 0, fontSize: '0.85rem', color: 'rgba(255,255,255,0.5)' }}>Real-time token logs and cost audit trails</p>
            </div>

            <div className="glass-card admin-table-card" style={{ marginTop: '1rem', maxHeight: '400px', overflowY: 'auto' }}>
              <table className="admin-projects-table">
                <thead>
                  <tr>
                    <th>Student Email</th>
                    <th>API Action / Generated Unit</th>
                    <th>Input Tokens</th>
                    <th>Output Tokens</th>
                    <th>Estimated Cost</th>
                    <th>Timestamp</th>
                  </tr>
                </thead>
                <tbody>
                  {usageStats.logs.map((log) => (
                    <tr key={log.id}>
                      <td style={{ fontWeight: 600, color: 'rgba(255,255,255,0.9)' }}>{log.email}</td>
                      <td style={{ color: '#00f2fe' }}>{log.action}</td>
                      <td>{parseInt(log.input_tokens || 0).toLocaleString()}</td>
                      <td>{parseInt(log.output_tokens || 0).toLocaleString()}</td>
                      <td style={{ fontWeight: 600, color: '#10b981' }}>${parseFloat(log.cost || 0).toFixed(4)}</td>
                      <td style={{ color: 'rgba(255,255,255,0.4)', fontSize: '0.8rem' }}>{log.created_at}</td>
                    </tr>
                  ))}
                  {usageStats.logs.length === 0 && (
                    <tr>
                      <td colSpan="6" style={{ textAlign: 'center', padding: '3rem', color: '#94a3b8' }}>
                        No API activity logs recorded yet.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </motion.div>
        )}

        {/* --- SCENE 4: MAIN PROJECT GENERATOR (STEPPER) --- */}
        {user && isGenerating && (
          <div className="generator-wrapper">
            <header style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', marginBottom: '4rem', position: 'relative' }}>
              {/* Back to Dashboard Header Button with Auto-Save */}
              <button 
                onClick={async () => {
                  if (formData.title && formData.title.trim()) {
                    await handleSaveChanges();
                  }
                  setLoading(false); 
                  setIsGenerating(false); 
                  loadProjects(); 
                }} 
                className="btn-back-dashboard"
              >
                <ArrowLeft size={16} /> Save & Exit to Dashboard
              </button>

              <motion.div 
                initial={{ scale: 0 }}
                animate={{ scale: 1 }}
                transition={{ type: 'spring', stiffness: 260, damping: 20 }}
                style={{ marginBottom: '1.5rem', display: 'inline-block', padding: '1rem', background: 'rgba(255, 42, 95, 0.1)', borderRadius: '24px' }}
              >
                <Sparkles size={40} color="#ff2a5f" />
              </motion.div>
              
              <motion.h1 
                initial={{ opacity: 0, y: -20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.2, duration: 0.6 }}
                style={{ 
                  fontSize: '3.5rem', 
                  fontWeight: 800, 
                  background: 'linear-gradient(to right, #ff2a5f, #ff7b00, #00d2ff)', 
                  WebkitBackgroundClip: 'text', 
                  WebkitTextFillColor: 'transparent',
                  letterSpacing: '-1px',
                  lineHeight: 1.1,
                  textAlign: 'center'
                }}
              >
                Project AI Studio
              </motion.h1>
              <StepIndicator />
            </header>

            <AnimatePresence mode="wait">
              {step === 0 && (
                <motion.div 
                  key="step0"
                  variants={containerVariants}
                  initial="hidden"
                  animate="visible"
                  exit="exit"
                  className="glass-card"
                >
                  <motion.h2 variants={itemVariants} style={{ marginBottom: '2rem', display: 'flex', alignItems: 'center', gap: '0.75rem', fontSize: '1.8rem' }}>
                    <Zap size={28} color="#00d2ff" /> Initiate Project
                  </motion.h2>
                  <form onSubmit={goToPhase1}>
                    <motion.div variants={itemVariants} className="input-group">
                      <label>Project Title</label>
                      <input 
                        type="text" 
                        name="title" 
                        placeholder="e.g. Quantum Cryptography Networks" 
                        value={formData.title}
                        onChange={handleInputChange}
                        required
                      />
                    </motion.div>
                    
                    <motion.div variants={itemVariants} className="input-group">
                      <label>Domain</label>
                      <input 
                        type="text" 
                        name="domain" 
                        placeholder="e.g. Cybersecurity, AI, Web3" 
                        value={formData.domain}
                        onChange={handleInputChange}
                        required
                      />
                    </motion.div>

                    <motion.div variants={itemVariants} style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))', gap: '1.5rem', marginBottom: '2.5rem' }}>
                      <div className="input-group" style={{ marginBottom: 0 }}>
                        <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                          <FileText size={18} color="#a1a1aa" /> Report Template (DOCX)
                        </label>
                        <div className="file-upload-wrapper">
                          <input type="file" name="reportTemplate" accept=".docx" onChange={handleFileChange} />
                          <div className="file-upload-design">
                            <Upload size={28} color={formData.reportTemplate ? "#00d2ff" : "#a1a1aa"} />
                            <span style={{ fontSize: '1rem' }}>Upload DOCX</span>
                            {formData.reportTemplate && <div className="file-name">{formData.reportTemplate.name}</div>}
                          </div>
                        </div>
                      </div>
                      <div className="input-group" style={{ marginBottom: 0 }}>
                        <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                          <Presentation size={18} color="#a1a1aa" /> Slide Template (PPTX)
                        </label>
                        <div className="file-upload-wrapper">
                          <input type="file" name="pptTemplate" accept=".pptx" onChange={handleFileChange} />
                          <div className="file-upload-design">
                            <Layers size={28} color={formData.pptTemplate ? "#ff2a5f" : "#a1a1aa"} />
                            <span style={{ fontSize: '1rem' }}>Upload PPTX</span>
                            {formData.pptTemplate && <div className="file-name">{formData.pptTemplate.name}</div>}
                          </div>
                        </div>
                      </div>
                    </motion.div>

                    <motion.div variants={itemVariants}>
                      <button type="submit" className="btn-primary" style={{ width: '100%', justifyContent: 'center' }} disabled={loading}>
                        {loading ? <RefreshCw className="animate-spin" /> : <><Sparkles size={20} /> Next: Problem Identification</>}
                      </button>
                    </motion.div>
                  </form>
                </motion.div>
              )}

              {step === 1 && (
                <motion.div key="step1" variants={containerVariants} initial="hidden" animate="visible" exit="exit" className="glass-card">
                  <motion.h2 variants={itemVariants} style={{ marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.75rem', fontSize: '1.8rem' }}>
                    <Brain size={28} color="#ff2a5f" /> Phase 1: Problem Identification
                  </motion.h2>
                  <motion.p variants={itemVariants} style={{ color: 'var(--text-dim)', marginBottom: '2rem' }}>
                    {phaseData.explanation}
                  </motion.p>

                  <div className="options-grid">
                    {phaseData.options.map((opt, i) => (
                      <motion.div 
                        key={i}
                        variants={itemVariants}
                        className={`option-card ${formData.problem === opt ? 'selected' : ''}`}
                        onClick={() => setFormData({...formData, problem: opt})}
                      >
                        <div className="option-icon"><Target size={20} /></div>
                        <p>{opt}</p>
                      </motion.div>
                    ))}
                  </div>

                  <motion.div variants={itemVariants} className="input-group" style={{ marginTop: '2rem' }}>
                    <label>Or define your own problem statement</label>
                    <textarea 
                      name="problem" 
                      rows="3" 
                      placeholder="Type your specific research problem here..."
                      value={formData.problem}
                      onChange={handleInputChange}
                    ></textarea>
                  </motion.div>

                  <div style={{ display: 'flex', gap: '1rem', marginTop: '2rem' }}>
                    <button onClick={() => { setLoading(false); setStep(0); }} className="btn-secondary">Back</button>
                    <button onClick={goToPhase2} className="btn-primary" style={{ flex: 1, justifyContent: 'center' }} disabled={loading}>
                      {loading ? <RefreshCw className="animate-spin" /> : <><Sparkles size={20} /> Next: Methodology</>}
                    </button>
                  </div>
                </motion.div>
              )}

              {step === 2 && (
                <motion.div key="step2" variants={containerVariants} initial="hidden" animate="visible" exit="exit" className="glass-card">
                  <motion.h2 variants={itemVariants} style={{ marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.75rem', fontSize: '1.8rem' }}>
                    <Code size={28} color="#00d2ff" /> Phase 2: Methodology
                  </motion.h2>
                  <motion.p variants={itemVariants} style={{ color: 'var(--text-dim)', marginBottom: '2rem' }}>
                    {phaseData.explanation}
                  </motion.p>

                  <div className="options-grid">
                    {phaseData.options.map((opt, i) => (
                      <motion.div 
                        key={i}
                        variants={itemVariants}
                        className={`option-card ${formData.methodology === opt ? 'selected' : ''}`}
                        onClick={() => setFormData({...formData, methodology: opt})}
                      >
                        <div className="option-icon"><Layers size={20} /></div>
                        <p>{opt}</p>
                      </motion.div>
                    ))}
                  </div>

                  <motion.div variants={itemVariants} className="input-group" style={{ marginTop: '2rem' }}>
                    <label>Or specify your methodology</label>
                    <textarea 
                      name="methodology" 
                      rows="3" 
                      placeholder="Describe your technical approach, algorithms, or framework..."
                      value={formData.methodology}
                      onChange={handleInputChange}
                    ></textarea>
                  </motion.div>

                  <div style={{ display: 'flex', gap: '1rem', marginTop: '2rem' }}>
                    <button onClick={() => { setLoading(false); setStep(1); }} className="btn-secondary">Back</button>
                    <button onClick={goToPhase3} className="btn-primary" style={{ flex: 1, justifyContent: 'center' }} disabled={loading}>
                      <Sparkles size={20} /> Next: Results & Data
                    </button>
                  </div>
                </motion.div>
              )}

              {step === 3 && (
                <motion.div key="step3" variants={containerVariants} initial="hidden" animate="visible" exit="exit" className="glass-card">
                  <motion.h2 variants={itemVariants} style={{ marginBottom: '1rem', display: 'flex', alignItems: 'center', gap: '0.75rem', fontSize: '1.8rem' }}>
                    <ImageIcon size={28} color="#ff7b00" /> Phase 3: Results & Visualization
                  </motion.h2>
                  
                  <motion.button 
                    variants={itemVariants}
                    onClick={generateAIResults}
                    className="btn-ai-generate"
                    disabled={loading}
                    style={{ marginBottom: '2rem' }}
                  >
                    {loading ? <RefreshCw className="animate-spin" /> : <><Sparkles size={18} /> Simulate Implementation & Generate Results</>}
                  </motion.button>

                  <motion.div variants={itemVariants} className="input-group">
                    <label>Results Summary</label>
                    <textarea 
                      name="resultsText" 
                      rows="5" 
                      placeholder="Describe key findings or use the AI generator above..."
                      value={formData.resultsText}
                      onChange={handleInputChange}
                    ></textarea>
                  </motion.div>

                  {formData.architectureDiagram && (
                    <motion.div variants={itemVariants} className="input-group">
                      <label>Architecture & Process Simulation</label>
                      <div className="architecture-preview-card">
                        <div className="arch-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                          <span style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                            <Layers size={18} /> {formData.architectureDiagram.title}
                          </span>
                          <button 
                            type="button" 
                            className="btn-download-arch"
                            onClick={() => {
                              const url = `https://quickchart.io/graphviz?format=png&graph=${encodeURIComponent(formData.architectureDiagram.diagramConfig)}`;
                              window.open(url, '_blank');
                            }}
                            style={{
                              background: 'rgba(255, 255, 255, 0.1)',
                              border: '1px solid rgba(255, 255, 255, 0.2)',
                              color: 'white',
                              padding: '4px 10px',
                              borderRadius: '6px',
                              fontSize: '0.75rem',
                              fontWeight: '600',
                              cursor: 'pointer',
                              display: 'flex',
                              alignItems: 'center',
                              gap: '4px',
                              transition: 'all 0.2s'
                            }}
                          >
                            <Download size={12} /> Download PNG
                          </button>
                        </div>
                        <img 
                          src={`https://quickchart.io/graphviz?format=png&graph=${encodeURIComponent(formData.architectureDiagram.diagramConfig)}`} 
                          alt="Architecture" 
                          onClick={() => {
                            setActivePreviewImage({
                              url: `https://quickchart.io/graphviz?format=png&graph=${encodeURIComponent(formData.architectureDiagram.diagramConfig)}`,
                              title: formData.architectureDiagram.title
                            });
                          }}
                          style={{ 
                            display: 'block',
                            margin: '0 auto',
                            maxWidth: '100%', 
                            maxHeight: '450px',
                            background: 'white', 
                            padding: '1rem', 
                            borderRadius: '8px',
                            objectFit: 'contain',
                            cursor: 'zoom-in'
                          }}
                        />
                      </div>
                    </motion.div>
                  )}

                  <motion.div variants={itemVariants} className="input-group">
                    <label>List of Figures ({formData.resultImages.length})</label>
                    <div style={{ display: 'grid', gap: '1rem', marginBottom: '1.5rem' }}>
                      {formData.resultImages.map((img, i) => (
                        <div key={img.id} className="figure-item-card">
                          <div className="figure-number">Figure {i + 1}</div>
                          <div className="figure-preview-small" onClick={() => {
                            if (img.type === 'ai' && img.chartConfig) {
                              setActivePreviewImage({
                                url: `https://quickchart.io/chart?c=${encodeURIComponent(JSON.stringify(img.chartConfig))}`,
                                title: img.title
                              });
                            }
                          }} style={{ cursor: img.type === 'ai' ? 'zoom-in' : 'default' }}>
                            {img.type === 'ai' && img.chartConfig ? (
                              <img 
                                src={`https://quickchart.io/chart?c=${encodeURIComponent(JSON.stringify(img.chartConfig))}`} 
                                alt={img.title}
                              />
                            ) : (
                              <div className="no-preview"><ImageIcon size={16} /></div>
                            )}
                          </div>
                          <div className="figure-details">
                            <div className="figure-title">{img.title}</div>
                            <div className="figure-desc">{img.description || 'Student Uploaded Image'}</div>
                          </div>
                          <button onClick={() => removeImage(img.id)} className="remove-fig-btn"><X size={16} /></button>
                        </div>
                      ))}
                    </div>

                    <div className="file-upload-wrapper" style={{ height: '120px' }}>
                      <input type="file" name="resultImages" accept="image/*" multiple onChange={handleFileChange} />
                      <div className="file-upload-design" style={{ padding: '1rem' }}>
                        <Plus size={24} />
                        <span>Attach your own images/graphs</span>
                      </div>
                    </div>
                  </motion.div>

                  <div style={{ display: 'flex', gap: '1rem', marginTop: '2rem' }}>
                    <button onClick={() => { setLoading(false); setStep(2); }} className="btn-secondary">Back</button>
                    <button onClick={handleGenerate} className="btn-primary" style={{ flex: 1, justifyContent: 'center' }} disabled={loading}>
                      {loading ? <RefreshCw className="animate-spin" /> : <><Zap size={20} /> Generate Final Report</>}
                    </button>
                  </div>

                  {loading && (
                    <div style={{ marginTop: '2rem', background: 'rgba(0,0,0,0.4)', padding: '1.5rem', borderRadius: '16px', border: '1px solid rgba(255,255,255,0.05)' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '1rem', fontSize: '1rem', fontWeight: 500, color: '#00d2ff' }}>
                        <span className="animate-pulse">{loadingText}</span>
                        <span>{Math.round(progress)}%</span>
                      </div>
                      <div style={{ width: '100%', height: '12px', background: 'rgba(255,255,255,0.05)', borderRadius: '6px', overflow: 'hidden' }}>
                        <motion.div 
                          initial={{ width: 0 }}
                          animate={{ width: `${progress}%` }}
                          style={{ height: '100%', background: 'linear-gradient(90deg, #ff2a5f, #00d2ff)' }}
                        />
                      </div>
                    </div>
                  )}
                </motion.div>
              )}

              {step === 4 && generatedContent && (
                <motion.div key="step4" variants={containerVariants} initial="hidden" animate="visible" className="preview-section">
                  <div className="glass-card" style={{ marginBottom: '2rem' }}>
                    <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: 'center', gap: '1rem', marginBottom: '3rem', borderBottom: '1px solid rgba(255,255,255,0.1)', paddingBottom: '1.5rem' }}>
                      <h2 style={{ fontSize: '2rem', display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                        <CheckCircle size={32} color="#00d2ff" /> Phase 4: Final Review
                      </h2>
                      <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap' }}>
                        <button onClick={handleSaveChanges} style={{ background: 'rgba(255,255,255,0.08)', border: '1px solid rgba(255,255,255,0.2)', color: 'white', padding: '0.85rem 1.5rem', borderRadius: '12px', cursor: 'pointer', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '0.5rem', transition: 'all 0.2s' }}>
                          Save Draft
                        </button>
                        <button onClick={() => setShowPreviewModal(true)} style={{ background: 'linear-gradient(90deg, #ff2a5f, #ff7b00)', color: 'white', border: 'none', padding: '0.85rem 1.5rem', borderRadius: '12px', cursor: 'pointer', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '0.5rem', transition: 'all 0.2s', boxShadow: '0 4px 15px rgba(255, 42, 95, 0.3)' }}>
                          <Eye size={20} /> Preview
                        </button>
                        <button onClick={handleFinalize} className="btn-primary" disabled={loading}>
                          {loading ? <RefreshCw className="animate-spin" /> : <><Download size={20} /> Download Report</>}
                        </button>
                      </div>
                    </div>

                    {showPreviewModal && (
                      <div className="preview-modal-overlay" onClick={() => setShowPreviewModal(false)}>
                        <div className="preview-modal-content" onClick={e => e.stopPropagation()}>
                          <div className="preview-modal-header">
                            <h3 style={{ color: '#0f172a', margin: 0, display: 'flex', alignItems: 'center', gap: '0.5rem' }}><Eye size={24} color="#ff2a5f" /> Document Preview</h3>
                            <button onClick={() => setShowPreviewModal(false)}><X size={24} /></button>
                          </div>
                          <div className="preview-document-body">
                            <h1 style={{ textAlign: 'center', marginBottom: '2rem', fontWeight: 'bold' }}>{formData.title}</h1>
                            <h2 style={{ marginBottom: '1rem', textAlign: 'center', fontWeight: 'bold' }}>ABSTRACT</h2>
                            <div dangerouslySetInnerHTML={{ __html: generatedContent.abstract || 'N/A' }} style={{ marginBottom: '2rem', textAlign: 'justify' }} />
                            
                            <div className="preview-page-break-indicator">PAGE BREAK</div>
                            <h2 style={{ marginBottom: '1rem', textAlign: 'center', fontWeight: 'bold' }}>LIST OF FIGURES</h2>
                            <table style={{ width: '100%', marginBottom: '2rem', fontSize: '12pt', borderCollapse: 'collapse' }}>
                              <thead>
                                <tr>
                                  <th style={{ textAlign: 'left', paddingBottom: '10px' }}>FIGURE NO.</th>
                                  <th style={{ textAlign: 'left', paddingBottom: '10px' }}>TITLE</th>
                                  <th style={{ textAlign: 'right', paddingBottom: '10px' }}>PAGE NO.</th>
                                </tr>
                              </thead>
                              <tbody>
                                {(generatedContent.figures || []).map((fig, i) => (
                                  <tr key={i}>
                                    <td style={{ paddingTop: '8px' }}>Figure {fig.id}</td>
                                    <td style={{ paddingTop: '8px' }}>{fig.title}</td>
                                    <td style={{ paddingTop: '8px', textAlign: 'right' }}>{10 + i}</td>
                                  </tr>
                                ))}
                              </tbody>
                            </table>

                            {generatedContent.sections.map((sec, i) => (
                              <div key={i} style={{ marginBottom: '4rem' }}>
                                <div className="preview-page-break-indicator">PAGE BREAK</div>
                                <h3 style={{ textAlign: 'center', fontWeight: 'normal', fontSize: '1.2rem', marginBottom: '5px', marginTop: '2rem' }}>CHAPTER {i + 1}</h3>
                                <h2 style={{ textAlign: 'center', marginBottom: '2rem', textTransform: 'uppercase' }}>{sec.heading}</h2>
                                <div dangerouslySetInnerHTML={{ __html: sec.content }} className="ql-editor" style={{ padding: 0, textAlign: 'justify' }} />
                                
                                {sec.heading.toLowerCase().includes('result') && (
                                  <div style={{ marginTop: '2rem' }}>
                                    {(generatedContent.figures || []).map((fig, idx) => (
                                      <div key={idx} style={{ textAlign: 'center', marginBottom: '2rem' }}>
                                        {fig.type === 'ai' && fig.chartConfig ? (
                                          <img 
                                            src={`https://quickchart.io/chart?c=${encodeURIComponent(JSON.stringify(fig.chartConfig))}`} 
                                            alt={fig.title}
                                            style={{ maxWidth: '100%', borderRadius: '8px', border: '1px solid #e2e8f0' }}
                                          />
                                        ) : (
                                          <div style={{ width: '100%', height: '200px', background: '#f1f5f9', border: '1px dashed #cbd5e1', borderRadius: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#64748b' }}>
                                            [Image: {fig.title}]
                                          </div>
                                        )}
                                        <p style={{ fontStyle: 'italic', marginTop: '0.5rem', fontWeight: 'bold' }}>Figure {fig.id}: {fig.title}</p>
                                      </div>
                                    ))}
                                  </div>
                                )}
                              </div>
                            ))}
                          </div>
                        </div>
                      </div>
                    )}

                    <div style={{ display: 'grid', gap: '2.5rem' }}>
                      <div className="editable-content">
                        <h4 style={{ color: '#00d2ff', marginBottom: '1rem', fontSize: '1.2rem' }}>Abstract</h4>
                        <SimpleEditor value={generatedContent.abstract} onChange={updateAbstract} />
                      </div>
                      {generatedContent.sections.map((sec, i) => (
                        <div key={i} className="editable-content">
                          <input type="text" className="chapter-heading-input" value={sec.heading} onChange={(e) => updateSectionHeading(i, e.target.value)} />
                          <SimpleEditor value={sec.content} onChange={(value) => updateSectionContent(i, value)} />
                        </div>
                      ))}
                      <button onClick={addNewChapter} className="btn-add-chapter">
                        <Plus size={22} /> Add New Chapter
                      </button>
                    </div>
                  </div>
                </motion.div>
              )}
            </AnimatePresence>
          </div>
        )}

        <style>{`
          /* Dynamic Background Floating Particles */
          .background-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: #090d16;
            z-index: -1;
            overflow: hidden;
          }
          .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.45;
            mix-blend-mode: screen;
            animation: drift 25s infinite alternate ease-in-out;
          }
          .orb-1 { width: 45vw; height: 45vw; background: radial-gradient(circle, #ff2a5f 0%, rgba(255, 42, 95, 0) 70%); top: -10vw; left: -10vw; }
          .orb-2 { width: 50vw; height: 50vw; background: radial-gradient(circle, #00d2ff 0%, rgba(0, 210, 255, 0) 70%); bottom: -10vw; right: -10vw; animation-delay: -5s; }
          .orb-3 { width: 40vw; height: 40vw; background: radial-gradient(circle, #ff7b00 0%, rgba(255, 123, 0, 0) 70%); top: 40vh; left: 30vw; animation-delay: -10s; }
          .orb-4 { width: 35vw; height: 35vw; background: radial-gradient(circle, #7b2cbf 0%, rgba(123, 44, 191, 0) 70%); bottom: 30vh; left: -5vw; animation-delay: -15s; }
          
          @keyframes drift {
            0% { transform: translateY(0) scale(1); }
            50% { transform: translateY(60px) scale(1.1); }
            100% { transform: translateY(-30px) scale(0.95); }
          }

          /* General styling */
          .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem 6rem 1.5rem; position: relative; z-index: 1; }
          
          /* Stepper design */
          .step-indicator-container { display: flex; justify-content: center; margin-top: 2rem; margin-bottom: 2.5rem; width: 100%; }
          .step-indicator { display: flex; align-items: center; justify-content: center; gap: 0.25rem; width: 100%; max-width: 650px; }
          .step-dot-wrapper { display: flex; flex-direction: column; align-items: center; position: relative; width: 32px; height: 32px; }
          .step-dot { width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; color: var(--text-dim); transition: all 0.3s; z-index: 2; }
          .step-dot.clickable:hover { transform: scale(1.15); border-color: var(--accent-secondary); box-shadow: 0 0 10px rgba(255, 42, 95, 0.4); }
          .step-dot.active { background: var(--accent-primary); border-color: var(--accent-primary); color: white; }
          .step-dot.current { box-shadow: 0 0 15px var(--accent-primary); transform: scale(1.1); }
          .step-line { height: 2px; flex-grow: 1; min-width: 20px; max-width: 80px; background: rgba(255,255,255,0.1); z-index: 1; }
          .step-line.active { background: var(--accent-primary); }
          .step-label { font-size: 0.65rem; font-weight: 600; color: var(--text-dim); text-align: center; white-space: nowrap; position: absolute; top: 38px; left: 50%; transform: translateX(-50%); opacity: 0.55; transition: all 0.3s; pointer-events: none; }
          .step-label.active { color: white; opacity: 0.85; }
          .step-label.current { color: var(--accent-secondary); font-weight: 700; opacity: 1; }

          /* Auth Portal Styles */
          .auth-portal-wrapper { display: flex; justify-content: center; align-items: center; min-height: 80vh; padding: 1rem; }
          .auth-card { max-width: 480px; width: 100%; padding: 3rem 2.5rem; text-align: center; }
          .auth-header-logo { margin-bottom: 2.5rem; }
          .logo-sparkle-box { display: inline-block; padding: 1rem; background: rgba(255, 42, 95, 0.08); border-radius: 20px; border: 1px solid rgba(255, 42, 95, 0.15); margin-bottom: 1.25rem; }
          .auth-header-logo h2 { font-size: 2.2rem; font-weight: 800; background: linear-gradient(135deg, #fff 30%, #a1a1aa 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
          .auth-header-logo p { color: #94a3b8; font-size: 0.95rem; margin-top: 0.5rem; font-weight: 500; }
          
          .auth-toggle-bar { display: flex; background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.05); padding: 5px; border-radius: 12px; margin-bottom: 2rem; }
          .toggle-btn { flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; border: none; background: none; color: #94a3b8; padding: 10px; font-weight: 600; font-size: 0.9rem; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
          .toggle-btn.active { background: rgba(255, 42, 95, 0.12); border: 1px solid rgba(255, 42, 95, 0.2); color: white; }
          
          .auth-input-wrapper { display: flex; align-items: center; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 0 1rem; margin-top: 0.5rem; transition: border 0.2s; }
          .auth-input-wrapper:focus-within { border-color: #ff2a5f; box-shadow: 0 0 10px rgba(255, 42, 95, 0.25); }
          .auth-icon { color: #64748b; margin-right: 0.75rem; }
          .auth-input-wrapper input { border: none; background: none; outline: none; width: 100%; padding: 14px 0; color: white; font-size: 1rem; }
          
          .auth-submit-btn { width: 100%; justify-content: center; margin-top: 2rem; padding: 14px 20px; font-size: 1.05rem; }
          .auth-footer-tag { font-size: 0.75rem; color: #64748b; margin-top: 1.5rem; display: flex; align-items: center; justify-content: center; gap: 6px; font-weight: 500; }

          /* Dashboard wrapper & banner */
          .dashboard-wrapper { display: flex; flex-direction: column; gap: 2.5rem; margin-top: 1rem; }
          .dashboard-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 2rem; }
          .db-welcome h2 { font-size: 2.2rem; font-weight: 800; color: white; }
          .db-welcome p { color: #94a3b8; font-size: 1.05rem; margin-top: 0.5rem; }
          .logout-btn { padding: 10px 18px; display: flex; align-items: center; gap: 6px; font-weight: 600; border-radius: 10px; font-size: 0.9rem; }
          .btn-back-dashboard { position: absolute; top: 0; left: 0; display: flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); color: white; padding: 10px 16px; border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.2s; font-size: 0.9rem; }
          .btn-back-dashboard:hover { background: rgba(255, 255, 255, 0.1); transform: translateX(-3px); }

          /* Stats Grid */
          .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; }
          .stat-card { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; padding: 1.5rem 2rem; display: flex; align-items: center; gap: 1.5rem; }
          .stat-card.accent { border-color: rgba(255, 42, 95, 0.2); background: radial-gradient(circle at top right, rgba(255, 42, 95, 0.05), transparent); }
          .stat-icon { background: rgba(255, 255, 255, 0.05); color: #00d2ff; padding: 1rem; border-radius: 16px; display: flex; align-items: center; justify-content: center; }
          .stat-card.accent .stat-icon { color: #ff2a5f; }
          .stat-info h3 { font-size: 2.2rem; font-weight: 800; color: white; line-height: 1.1; }
          .stat-info p { color: #94a3b8; font-size: 0.95rem; font-weight: 500; }

          /* Dashboard Search & Controls */
          .project-list-controls { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-top: 1rem; }
          .project-list-controls h3 { font-size: 1.5rem; font-weight: 700; color: white; }
          .search-bar-wrapper { display: flex; align-items: center; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 0 1rem; width: 100%; max-width: 380px; }
          .search-bar-wrapper input { border: none; background: none; outline: none; padding: 12px 0; color: white; font-size: 0.95rem; width: 100%; margin-left: 0.75rem; }
          .search-bar-wrapper input::placeholder { color: #64748b; }

          /* Dashboard grid */
          .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 2rem; }
          .project-card { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; padding: 2rem; cursor: pointer; display: flex; flex-direction: column; gap: 1.25rem; position: relative; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); min-height: 220px; }
          .project-card:hover { transform: translateY(-5px); background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.2); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); }
          .proj-card-icon { width: 50px; height: 50px; background: rgba(255, 255, 255, 0.05); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #ff2a5f; border: 1px solid rgba(255, 255, 255, 0.05); }
          .project-card:hover .proj-card-icon { transform: scale(1.1); color: #00d2ff; }
          .proj-card-content h4 { font-size: 1.25rem; font-weight: 700; color: white; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.3; }
          .proj-domain { font-size: 0.85rem; font-weight: 700; color: #ff7b00; text-transform: uppercase; letter-spacing: 0.5px; }
          .proj-meta { display: flex; justify-content: space-between; align-items: center; margin-top: auto; font-size: 0.8rem; color: #64748b; font-weight: 500; }
          
          /* Resume & delete hover actions */
          .proj-card-hover-actions { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 1rem; margin-top: auto; }
          .btn-resume { background: none; border: none; color: #00d2ff; font-weight: 700; font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; gap: 4px; padding: 0; }
          .btn-resume:hover { text-shadow: 0 0 10px rgba(0, 210, 255, 0.5); }
          .btn-delete { background: rgba(255, 42, 95, 0.1); border: 1px solid rgba(255, 42, 95, 0.2); color: #ff2a5f; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
          .btn-delete:hover { background: #ff2a5f; color: white; }

          /* Create New CTA Card special style */
          .create-cta-card { border: 2px dashed rgba(0, 210, 255, 0.3); background: rgba(0, 210, 255, 0.01); display: flex; align-items: center; justify-content: center; text-align: center; }
          .create-cta-card:hover { border-color: #00d2ff; background: rgba(0, 210, 255, 0.03); }
          .create-cta-sparkle { background: rgba(0, 210, 255, 0.1); padding: 1.25rem; border-radius: 50%; margin-bottom: 0.5rem; }
          .create-cta-card h4 { font-size: 1.3rem; font-weight: 800; color: white; }
          .create-cta-card p { font-size: 0.9rem; color: #94a3b8; line-height: 1.5; padding: 0 1rem; }

          /* Admin Table Panel styling */
          .admin-table-card { padding: 0; overflow: hidden; border-radius: 20px; }
          .admin-projects-table { width: 100%; border-collapse: collapse; border-spacing: 0; text-align: left; }
          .admin-projects-table th { background: rgba(0, 0, 0, 0.2); padding: 1.25rem 1.5rem; font-size: 0.9rem; font-weight: 700; color: #94a3b8; border-bottom: 1px solid rgba(255, 255, 255, 0.08); }
          .admin-projects-table tbody tr { border-bottom: 1px solid rgba(255, 255, 255, 0.04); cursor: pointer; transition: all 0.2s; }
          .admin-projects-table tbody tr:hover { background: rgba(255, 255, 255, 0.03); }
          .admin-projects-table td { padding: 1.25rem 1.5rem; font-size: 0.95rem; vertical-align: middle; }
          .admin-td-email { font-weight: 700; color: #00d2ff; }
          .admin-td-title { font-weight: 600; color: white; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
          .admin-domain-badge { background: rgba(255, 123, 0, 0.1); border: 1px solid rgba(255, 123, 0, 0.2); color: #ff7b00; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.8rem; }
          .admin-td-date { color: #64748b; font-weight: 500; }
          .admin-action-btn { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); color: white; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s; }
          .admin-action-btn:hover { background: #00d2ff; color: #090d16; border-color: #00d2ff; }
          .admin-action-btn.delete { color: #ff2a5f; background: rgba(255, 42, 95, 0.08); border-color: rgba(255, 42, 95, 0.15); }
          .admin-action-btn.delete:hover { background: #ff2a5f; color: white; border-color: #ff2a5f; }

          .empty-search-state { text-align: center; padding: 4rem 2rem; background: rgba(255, 255, 255, 0.01); border-radius: 20px; border: 1px dashed rgba(255,255,255,0.05); color: #64748b; }

          /* Options grid */
          .options-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
          .option-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 1.25rem; cursor: pointer; display: flex; gap: 1rem; align-items: flex-start; transition: all 0.2s; }
          .option-card:hover { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.2); transform: translateX(5px); }
          .option-card.selected { background: rgba(255, 42, 95, 0.1); border-color: var(--accent-primary); }
          .option-icon { background: rgba(255,255,255,0.05); padding: 0.5rem; border-radius: 10px; color: var(--accent-primary); }
          
          .btn-secondary { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: white; padding: 1rem 2rem; border-radius: 100px; cursor: pointer; font-weight: 600; }
          
          .btn-ai-generate { background: linear-gradient(135deg, #7b2cbf 0%, #ff2a5f 100%); color: white; border: none; padding: 1rem 2rem; border-radius: 16px; cursor: pointer; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 0.75rem; width: 100%; transition: all 0.3s; box-shadow: 0 10px 20px rgba(123, 44, 191, 0.3); }
          .btn-ai-generate:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(123, 44, 191, 0.4); }

          .figure-item-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 1rem; display: flex; align-items: center; gap: 1rem; position: relative; }
          .figure-number { background: var(--accent-secondary); color: white; padding: 0.25rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.8rem; }
          .figure-preview-small { width: 80px; height: 50px; background: rgba(0,0,0,0.2); border-radius: 6px; overflow: hidden; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1); }
          .figure-preview-small img { width: 100%; height: 100%; object-fit: cover; }
          .figure-preview-small .no-preview { color: #4b5563; }
          .figure-details { flex: 1; }
          .figure-title { font-weight: 600; color: white; font-size: 1rem; }
          .figure-desc { font-size: 0.85rem; color: var(--text-dim); }
          .remove-fig-btn { background: rgba(255, 42, 95, 0.1); color: var(--accent-primary); border: none; padding: 0.5rem; border-radius: 8px; cursor: pointer; }

          .preview-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 1000; display: flex; justify-content: center; align-items: center; padding: 2rem; backdrop-filter: blur(5px); }
          .preview-modal-content { background: white; width: 100%; max-width: 900px; max-height: 90vh; border-radius: 20px; overflow: hidden; display: flex; flex-direction: column; }
          .preview-modal-header { padding: 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
          .preview-document-body { padding: 3rem; overflow-y: auto; color: #1e293b; background: white; font-family: "Inter", serif; line-height: 1.6; }
          
          .btn-add-chapter { display: flex; align-items: center; justify-content: center; gap: 8px; background: linear-gradient(135deg, #00d2ff 0%, #3a7bd5 100%); color: #fff; border: none; padding: 14px 24px; border-radius: 12px; cursor: pointer; font-weight: bold; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(0, 210, 255, 0.3); transition: all 0.3s ease; }
          .btn-add-chapter:hover { transform: translateY(-2px); }
          
          .preview-page-break-indicator { border-top: 2px dashed #cbd5e1; position: relative; text-align: center; margin: 4rem 0 2rem 0; clear: both; }
          .preview-page-break-indicator::after { content: "PAGE BREAK"; position: absolute; top: -10px; left: 50%; transform: translateX(-50%); background: white; padding: 0 10px; font-size: 0.7rem; font-weight: 800; color: #94a3b8; letter-spacing: 1px; }

          .animate-spin { animation: spin 1s linear infinite; }
          @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        `}</style>
        
        {activePreviewImage && (
          <div className="preview-modal-overlay" onClick={() => setActivePreviewImage(null)} style={{ zIndex: 2000 }}>
            <motion.div 
              initial={{ scale: 0.9, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              className="glass-card" 
              onClick={e => e.stopPropagation()} 
              style={{ maxWidth: '800px', width: '90%', padding: '1rem', background: 'white' }}
            >
              <div style={{ display: 'flex', justify: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
                <h3 style={{ color: '#0f172a', margin: 0 }}>{activePreviewImage.title}</h3>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                  <button 
                    onClick={() => {
                      window.open(activePreviewImage.url, '_blank');
                    }}
                    style={{
                      background: 'linear-gradient(135deg, #7b2cbf 0%, #ff2a5f 100%)',
                      color: 'white',
                      border: 'none',
                      padding: '6px 12px',
                      borderRadius: '8px',
                      fontSize: '0.8rem',
                      fontWeight: 700,
                      cursor: 'pointer',
                      display: 'flex',
                      alignItems: 'center',
                      gap: '4px',
                      boxShadow: '0 4px 10px rgba(123, 44, 191, 0.2)'
                    }}
                  >
                    <Download size={14} /> Download Image
                  </button>
                  <button onClick={() => setActivePreviewImage(null)} style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#64748b', padding: 0 }}><X size={24} /></button>
                </div>
              </div>
              <img src={activePreviewImage.url} alt="Large preview" style={{ width: '100%', height: 'auto', borderRadius: '12px' }} />
            </motion.div>
          </div>
        )}
      </div>
    </>
  );
}

export default App;
