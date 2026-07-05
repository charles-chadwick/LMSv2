<?php

namespace Database\Seeders;

use Illuminate\Support\Collection;

class ComputerScienceTitles
{
    /**
     * Curated, real computer-science course titles.
     *
     * @var list<string>
     */
    public const COURSE_TITLES = [
        'Introduction to Algorithms', 'Data Structures', 'Operating Systems',
        'Computer Networks', 'Database Systems', 'Compiler Construction',
        'Computer Architecture', 'Discrete Mathematics', 'Theory of Computation',
        'Artificial Intelligence', 'Machine Learning', 'Deep Learning',
        'Computer Graphics', 'Human-Computer Interaction', 'Software Engineering',
        'Distributed Systems', 'Cryptography and Security', 'Programming Languages',
        'Functional Programming', 'Object-Oriented Design', 'Web Development',
        'Mobile Application Development', 'Cloud Computing', 'Parallel Computing',
        'Embedded Systems', 'Digital Logic Design', 'Numerical Methods',
        'Linear Algebra for Computing', 'Probability and Statistics',
        'Natural Language Processing', 'Computer Vision', 'Reinforcement Learning',
        'Information Retrieval', 'Data Mining', 'Big Data Analytics',
        'Operating System Design', 'Network Security', 'Ethical Hacking',
        'Quantum Computing', 'Bioinformatics', 'Robotics', 'Game Development',
        'Computer Systems Engineering', 'Formal Methods', 'Automata Theory',
        'Graph Theory', 'Optimization', 'Signal Processing', 'Real-Time Systems',
        'Microservices Architecture', 'DevOps and Continuous Delivery',
        'Version Control and Collaboration', 'Introduction to Programming',
        'Advanced Algorithms', 'Computational Geometry', 'Blockchain Fundamentals',
        'Internet of Things', 'Computer Ethics', 'Information Systems',
        'Systems Programming',
    ];

    /**
     * Curated module-level topic titles.
     *
     * @var list<string>
     */
    public const MODULE_TOPICS = [
        'Foundations and Notation', 'Memory Management', 'Sorting and Searching',
        'Recursion and Iteration', 'Graphs and Trees', 'Dynamic Programming',
        'Greedy Algorithms', 'Hashing and Hash Tables', 'Concurrency and Threads',
        'Process Scheduling', 'File Systems', 'Virtual Memory', 'Network Protocols',
        'Transport Layer', 'Relational Modeling', 'Query Optimization',
        'Normalization', 'Transactions and Concurrency Control', 'Lexical Analysis',
        'Syntax Parsing', 'Semantic Analysis', 'Code Generation', 'Type Systems',
        'Boolean Logic', 'Combinational Circuits', 'Sequential Circuits',
        'Caching Strategies', 'Load Balancing', 'Fault Tolerance',
        'Encryption Fundamentals', 'Public Key Infrastructure',
        'Neural Network Basics', 'Feature Engineering', 'Model Evaluation',
        'Regularization Techniques', 'Vectorization', 'State Management',
        'Testing Strategies', 'Deployment Pipelines', 'Performance Profiling',
    ];

    /**
     * Curated lesson-level topic titles.
     *
     * @var list<string>
     */
    public const LESSON_TOPICS = [
        'Binary Search Trees', 'Red-Black Trees', 'AVL Trees', 'B-Trees',
        'Heaps and Priority Queues', 'Linked Lists', 'Stacks and Queues',
        'Hash Maps', 'Bloom Filters', 'Tries', 'Depth-First Search',
        'Breadth-First Search', "Dijkstra's Shortest Path", 'Bellman-Ford Algorithm',
        'Floyd-Warshall Algorithm', 'Minimum Spanning Trees', 'Topological Sorting',
        'Union-Find', 'Quicksort', 'Merge Sort', 'Heap Sort', 'Radix Sort',
        'Counting Sort', 'Binary Search', 'Two Pointers Technique', 'Sliding Window',
        'Memoization', 'Tabulation', 'Knapsack Problem', 'Longest Common Subsequence',
        'Edit Distance', 'Matrix Chain Multiplication', 'Deadlock Avoidance',
        'Mutual Exclusion', 'Semaphores and Monitors', 'Paging and Segmentation',
        'Page Replacement Algorithms', 'Context Switching', 'Interrupt Handling',
        'TCP Handshake', 'IP Addressing and Subnetting', 'Routing Algorithms',
        'DNS Resolution', 'HTTP and HTTPS', 'Socket Programming', 'SQL Joins',
        'Indexing Strategies', 'ACID Properties', 'Two-Phase Commit',
        'Deadlock Detection', 'Finite State Machines', 'Regular Expressions',
        'Context-Free Grammars', 'Abstract Syntax Trees', 'Register Allocation',
        'Garbage Collection', 'Reference Counting', 'Pointer Arithmetic',
        'Cache Coherence', 'Pipelining', 'Branch Prediction',
        'Floating Point Representation', "Two's Complement", 'Gradient Descent',
        'Backpropagation', 'Overfitting and Underfitting', 'Cross-Validation',
        'Convolutional Layers', 'Attention Mechanisms', 'Tokenization',
        'Word Embeddings', 'Public and Private Keys', 'Hashing and Salting',
        'Digital Signatures', 'SQL Injection Defense', 'Cross-Site Scripting',
        'RESTful Endpoints', 'Dependency Injection', 'Unit Testing Basics',
        'Continuous Integration',
    ];

    /**
     * Remaining, shuffled course titles yet to be handed out.
     *
     * @var Collection<int, string>|null
     */
    private static ?Collection $courseQueue = null;

    /**
     * How many times the course pool has been exhausted and reused.
     */
    private static int $overflow = 0;

    /**
     * A distinct course title while the pool lasts, then a numbered fallback.
     */
    public static function nextCourse(): string
    {
        if (self::$courseQueue === null) {
            self::$courseQueue = collect(self::COURSE_TITLES)->shuffle();
        }

        if (self::$courseQueue->isNotEmpty()) {
            return self::$courseQueue->pop();
        }

        self::$overflow++;

        return collect(self::COURSE_TITLES)->random().' '.self::$overflow;
    }

    /**
     * A random module-level topic title.
     */
    public static function nextModule(): string
    {
        return collect(self::MODULE_TOPICS)->random();
    }

    /**
     * A random lesson-level topic title.
     */
    public static function nextLesson(): string
    {
        return collect(self::LESSON_TOPICS)->random();
    }

    /**
     * Reset the course queue (used to keep test runs deterministic).
     */
    public static function reset(): void
    {
        self::$courseQueue = null;
        self::$overflow = 0;
    }
}
