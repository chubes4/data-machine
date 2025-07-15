# Data Machine Development Plan

## Current Major Initiatives

### 1. Action Scheduler Migration (High Priority)
**Goal**: Eliminate timeout/duplicate issues by making all long-running operations asynchronous

#### Phase 1: Output Handler Migration (CRITICAL) - IN PROGRESS
- **Problem**: Remote publishing timeouts causing duplicate posts
- **Solution**: Async output job queue
- [x] Create `dm_output_job_event` Action Scheduler hook
- [x] Modify orchestrator to queue output jobs after AI processing
- [x] Mark items as processed only after output succeeds
- [x] Add retry logic for failed output jobs (3 retries with exponential backoff)
- [ ] **Testing**: Verify timeout/duplicate resolution
- [ ] **Target**: Eliminate timeout duplicate posts

#### Phase 2: OpenAI API Migration
- **Problem**: AI API calls can be slow and block execution
- [ ] Create `dm_ai_job_event` Action Scheduler hook
- [ ] Split AI processing into separate async jobs
- [ ] Implement AI job retry logic with exponential backoff
- [ ] Add rate limiting compliance
- [ ] **Target**: Non-blocking AI processing

#### Phase 3: Input Handler Migration
- **Problem**: Large RSS feeds/slow APIs can timeout
- [ ] Create `dm_input_job_event` Action Scheduler hook
- [ ] Async input data fetching
- [ ] Handle large data sources with pagination
- [ ] **Target**: Reliable input processing

#### Phase 4: Enhanced Monitoring
- [ ] Custom Action Scheduler dashboard for Data Machine
- [ ] Failed job reporting and manual retry
- [ ] Job performance metrics
- [ ] **Target**: Better operational visibility

### 2. AI HTTP Client Library Migration (Medium Priority)
**Goal**: Replace scattered HTTP logic with unified, multi-provider AI client

#### Library Integration
- [ ] Add ai-http-client as git subtree to `/libraries/ai-http-client/`
- [ ] Include in main plugin file after Action Scheduler
- [ ] Create `Data_Machine_AI_Client_Adapter` wrapper class
- [ ] **Target**: Foundation for unified AI communication

#### API Class Replacement
- [ ] Replace `Data_Machine_API_OpenAI` with adapter
- [ ] Replace `Data_Machine_API_FactCheck` with adapter  
- [ ] Replace `Data_Machine_API_Finalize` with adapter
- [ ] Remove legacy API classes
- [ ] **Target**: Single unified AI interface

#### Provider System Enhancement
- [ ] Add provider selection UI (OpenAI, Anthropic, Gemini)
- [ ] Implement automatic fallback chains
- [ ] Migrate existing API keys to provider system
- [ ] Add provider-specific configuration options
- [ ] **Target**: Multi-provider flexibility and reliability

#### Advanced Features (Future)
- [ ] Streaming AI responses for real-time feedback
- [ ] Provider cost optimization algorithms
- [ ] Enhanced error reporting with provider context
- [ ] **Target**: Best-in-class AI integration

### 3. WordPress.org Compliance (Completed ✅)
- [x] Create comprehensive readme.txt with external service disclosure
- [x] Document all third-party APIs (OpenAI, Reddit, Facebook, Twitter, etc.)
- [x] Add proper plugin description and repository URL
- [x] Clean up verbose debug logging
- [x] Fix Action Scheduler initialization timing

### 4. Multi-Plugin Strategy
**Goal**: Standardize architecture across all Chris Huber plugins

#### Shared Libraries
- [ ] **ai-http-client**: Deploy to Wordsurf, Data Machine, future plugins
- [ ] **Action Scheduler**: Standardize async job processing
- [ ] **Common utilities**: Shared logging, encryption, OAuth helpers
- [ ] **Target**: Consistent architecture across plugin ecosystem

#### Cross-Plugin Benefits
- [ ] Unified AI provider management across all plugins
- [ ] Shared fallback provider chains
- [ ] Consistent error handling and logging patterns
- [ ] Reduced maintenance overhead per plugin
- [ ] **Target**: Plugin ecosystem synergy

## Implementation Timeline

### Week 1: Critical Fixes
- [ ] Complete Action Scheduler Phase 1 (Output Handler Migration)
- [ ] Fix remote publishing timeout/duplicate issues
- [ ] Deploy ai-http-client library integration

### Week 2: Core Migrations  
- [ ] Complete AI HTTP Client API class replacements
- [ ] Begin Action Scheduler Phase 2 (AI API Migration)
- [ ] Provider system UI implementation

### Week 3: Advanced Features
- [ ] Action Scheduler Phase 3 (Input Handler Migration)
- [ ] Multi-provider fallback configuration
- [ ] Enhanced monitoring dashboard

### Week 4: Polish & Testing
- [ ] Comprehensive testing of async workflows
- [ ] Performance optimization
- [ ] Documentation updates
- [ ] WordPress.org submission preparation

## Success Metrics

### Reliability
- [ ] Zero timeout-related duplicate posts
- [ ] 99%+ job completion rate
- [ ] Automatic fallback to secondary AI providers

### Performance  
- [ ] Non-blocking AI processing
- [ ] Background job processing via Action Scheduler
- [ ] Faster response times for user interactions

### Maintainability
- [ ] Single unified AI client interface
- [ ] Standardized async job patterns
- [ ] Shared library architecture across plugins

### User Experience
- [ ] Real-time job status feedback
- [ ] Provider choice and cost optimization
- [ ] Better error messages and recovery options

## Architecture Goals

### Current State
```
Synchronous: Job → Process Data → Fact Check → Finalize → Output
Problems: Timeouts, blocking, poor error recovery
```

### Target State
```
Asynchronous: Job → Queue AI Job → Queue Output Job → Complete
Benefits: Non-blocking, retry logic, better reliability
```

### Multi-Plugin Ecosystem
```
Data Machine ──┐
               ├── ai-http-client (shared)
Wordsurf ──────┤
               ├── Action Scheduler (shared)
Future Plugin ─┘
```

## Notes
- **Action Scheduler migration is critical** for resolving current timeout issues
- **AI HTTP Client provides foundation** for multi-provider reliability
- **Both initiatives support** the broader multi-plugin standardization strategy
- **Focus on backward compatibility** during all migrations
- **Comprehensive testing required** before WordPress.org submission