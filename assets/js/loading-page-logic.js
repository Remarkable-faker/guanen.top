// Loading 页面逻辑 (React)
const { useState, useEffect } = React;

// Mock motion组件 (如果项目中没有真正的 framer-motion)
const createMockMotion = () => {
    const mockMotion = {
        div: ({ children, className, initial, animate, exit, transition, ...props }) => {
            return React.createElement('div', { className, ...props }, children);
        },
        AnimatePresence: ({ children }) => {
            return children;
        }
    };
    return mockMotion;
};

const mockMotion = createMockMotion();

// AppleHelloEnglishEffect组件 - 只用于触发hello动画
const AppleHelloEnglishEffect = ({ speed = 1, onAnimationComplete }) => {
    useEffect(() => {
        // 这里会由hello-animation.js处理实际动画
        
        // 模拟动画完成事件
        const timer = setTimeout(() => {
            if (onAnimationComplete) {
                onAnimationComplete();
            }
        }, 4000 / speed); // 4秒为默认动画持续时间
        
        return () => clearTimeout(timer);
    }, [speed, onAnimationComplete]);
    
    return <div className="hello-animation-container"></div>;
};

// LoadingScreen组件
const LoadingScreen = ({ onLoadingComplete }) => {
    const [isLoading, setIsLoading] = useState(true);
    
    const handleAnimationComplete = () => {
        console.log('动画完成');
        // 延迟一小段时间，让用户看到动画效果
        setTimeout(() => {
            setIsLoading(false);
            if (onLoadingComplete) {
                onLoadingComplete();
            }
        }, 2000);
    };
    
    return (
        <mockMotion.div 
            className="min-h-screen bg-white text-black fixed inset-0 z-50"
            initial={{ opacity: 1 }}
            animate={{ opacity: isLoading ? 1 : 0 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.5 }}
        >
            {/* React加载屏幕只是一个备用方案，主要动画由hello-animation.js处理 */}
        </mockMotion.div>
    );
};

// 主应用组件
const App = () => {
    // 处理加载完成事件
    const handleLoadingComplete = () => {
        console.log('加载完成，准备跳转到主页');
        // 跳转到主页
        window.location.href = '../index.php';
    };
    
    return <LoadingScreen onLoadingComplete={handleLoadingComplete} />;
};

// 渲染App组件
const rootElement = document.getElementById('loading-screen-root');
if (rootElement) {
    ReactDOM.render(<App />, rootElement);
}
